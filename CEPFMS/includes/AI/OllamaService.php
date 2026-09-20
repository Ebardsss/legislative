<?php
declare(strict_types=1);

/**
 * includes/AI/OllamaService.php
 * Local Ollama AI implementation for CEPFMS.
 */
require_once __DIR__ . '/AIServiceInterface.php';
require_once dirname(__DIR__, 2) . '/config/ai_config.php';

class OllamaService implements AIServiceInterface
{
    private string $baseUrl;
    private string $model;
    private int $connectTimeout;
    private int $requestTimeout;

    public function __construct(
        string $baseUrl = OLLAMA_BASE_URL,
        string $model = OLLAMA_MODEL,
        int $connectTimeout = AI_CONNECT_TIMEOUT_SECONDS,
        int $requestTimeout = AI_REQUEST_TIMEOUT_SECONDS
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->connectTimeout = $connectTimeout;
        $this->requestTimeout = $requestTimeout;
    }

    /**
     * Diagnostic check of local Ollama server and configured model.
     */
    public function healthCheck(): array
    {
        $result = $this->curlRequest('GET', '/api/tags', null, 5, 5);

        if (!$result['success']) {
            return [
                'available' => false,
                'message' => 'Cannot connect to local Ollama at ' . $this->baseUrl . ': ' . ($result['error'] ?? 'Connection refused'),
                'models' => [],
                'model_installed' => false,
            ];
        }

        if (($result['http_status'] ?? 0) >= 400) {
            return [
                'available' => false,
                'message' => 'Ollama returned HTTP ' . (int)$result['http_status'] . '.',
                'models' => [],
                'model_installed' => false,
            ];
        }

        $data = json_decode((string)$result['body'], true);
        if (!is_array($data) || !isset($data['models']) || !is_array($data['models'])) {
            return [
                'available' => false,
                'message' => 'Ollama returned an unexpected model list format.',
                'models' => [],
                'model_installed' => false,
            ];
        }

        $installedModels = [];
        $configuredBase = explode(':', $this->model)[0];
        $modelInstalled = false;

        foreach ($data['models'] as $row) {
            $name = (string)($row['name'] ?? $row['model'] ?? '');
            if ($name === '') continue;
            $installedModels[] = $name;

            if ($name === $this->model || explode(':', $name)[0] === $configuredBase) {
                $modelInstalled = true;
            }
        }

        return [
            'available' => true,
            'message' => $modelInstalled
                ? "Ollama is active and model '{$this->model}' is ready."
                : "Ollama is active, but configured model '{$this->model}' is not pulled yet.",
            'models' => $installedModels,
            'model_installed' => $modelInstalled,
            'configured_model' => $this->model,
            'base_url' => $this->baseUrl,
        ];
    }

    /**
     * Multi-factor analysis of citizen submission.
     */
    public function analyzeSubmission(array $submission): array
    {
        $startedAt = microtime(true);
        $title = trim((string)($submission['title'] ?? ''));
        $details = trim((string)($submission['details'] ?? ''));
        $type = (string)($submission['submission_type'] ?? 'Complaint');
        $location = trim((string)($submission['location_text'] ?? $submission['barangay'] ?? ''));

        $fullText = "Type: {$type}\nTitle: {$title}\nLocation: {$location}\nDetails:\n{$details}";

        $prompt = <<<PROMPT
You are an AI Intake and Moderation Specialist for the City of Manila Citizen Engagement and Public Feedback Management System (CEPFMS).
Analyze the following citizen submission. It may be written in English, Filipino/Tagalog, or Taglish.

[BEGIN SUBMISSION]
{$fullText}
[END SUBMISSION]

OFFICIAL CATEGORIES (Choose EXACTLY ONE best match):
- General Public Service
- Infrastructure
- Public Safety
- Environment
- Health and Sanitation
- Transportation and Mobility
- Social Services
- Governance and Transparency

URGENCY LEVELS:
- Low: Informational, general suggestion, praise, or non-disruptive feedback.
- Normal: Standard service request, minor complaint, or routine neighborhood concern.
- High: Serious disruption, overflowing garbage, unlit streets, active road hazard, or persistent unresolved issue.
- Urgent: Immediate danger to life/safety, severe flood, fallen electrical wires, hazardous chemical/fire, public health emergency.

SENTIMENT:
- Positive: Compliment, praise, gratitude, or satisfaction.
- Neutral: Inquiry, matter-of-fact report, or constructive proposal.
- Negative: Dissatisfaction, frustration, complaint about service failure, or harm.

CONTENT SAFETY:
- Safe: Appropriate civic communication.
- Potential Profanity: Mild vulgarity or offensive words.
- Toxic / Abusive: Harassment, severe hate speech, or defamatory attacks.

ACTIONABILITY:
- Complete: Has sufficient detail (location and specific problem) for city offices to act.
- Needs Clarification: Vague, missing location, or unclear specifics.

Return ONLY a single valid JSON object with NO extra text or markdown formatting:
{
  "sentiment": "Positive" | "Neutral" | "Negative",
  "confidence_score": 75,
  "urgency_level": "Low" | "Normal" | "High" | "Urgent",
  "urgency_score": 60,
  "recommended_category": "Infrastructure",
  "recommended_office": "City Engineering Department",
  "content_safety": "Safe" | "Potential Profanity" | "Toxic / Abusive",
  "actionability": "Complete" | "Needs Clarification",
  "executive_summary": "<One concise sentence in English summarizing the citizen concern>",
  "key_topics": ["<topic 1>", "<topic 2>"],
  "risk_keywords": ["<hazard 1>"],
  "recommended_action": "<Brief recommended staff action>"
}
PROMPT;

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
            'keep_alive' => AI_MODEL_KEEP_ALIVE_MINUTES . 'm',
            'options' => [
                'temperature' => 0.1,
                'num_predict' => AI_MAX_RESPONSE_TOKENS,
            ],
        ];

        $res = $this->curlRequest('POST', '/api/generate', $payload, $this->connectTimeout, $this->requestTimeout);
        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

        if (!$res['success']) {
            return [
                'success' => false,
                'error' => $res['error'] ?? 'Failed to communicate with Ollama.',
                'duration_ms' => $durationMs,
            ];
        }

        $outer = json_decode((string)$res['body'], true);
        if (!is_array($outer) || !isset($outer['response'])) {
            return [
                'success' => false,
                'error' => 'Ollama returned an unexpected response structure.',
                'duration_ms' => $durationMs,
            ];
        }

        $parsed = json_decode((string)$outer['response'], true);
        if (!is_array($parsed)) {
            return [
                'success' => false,
                'error' => 'AI model response could not be parsed as JSON.',
                'duration_ms' => $durationMs,
            ];
        }

        $clean = [
            'success' => true,
            'sentiment' => in_array($parsed['sentiment'] ?? '', ['Positive', 'Neutral', 'Negative'], true) ? $parsed['sentiment'] : 'Neutral',
            'confidence_score' => (int)($parsed['confidence_score'] ?? 75),
            'urgency_level' => in_array($parsed['urgency_level'] ?? '', ['Low', 'Normal', 'High', 'Urgent'], true) ? $parsed['urgency_level'] : 'Normal',
            'urgency_score' => (int)($parsed['urgency_score'] ?? 50),
            'recommended_category' => trim((string)($parsed['recommended_category'] ?? 'General Public Service')),
            'recommended_office' => trim((string)($parsed['recommended_office'] ?? 'City Council Public Assistance Desk')),
            'content_safety' => in_array($parsed['content_safety'] ?? '', ['Safe', 'Potential Profanity', 'Toxic / Abusive'], true) ? $parsed['content_safety'] : 'Safe',
            'actionability' => in_array($parsed['actionability'] ?? '', ['Complete', 'Needs Clarification'], true) ? $parsed['actionability'] : 'Complete',
            'executive_summary' => trim((string)($parsed['executive_summary'] ?? $title)),
            'key_topics' => is_array($parsed['key_topics'] ?? null) ? array_slice($parsed['key_topics'], 0, 5) : [],
            'risk_keywords' => is_array($parsed['risk_keywords'] ?? null) ? array_slice($parsed['risk_keywords'], 0, 5) : [],
            'recommended_action' => trim((string)($parsed['recommended_action'] ?? 'Review submission details and assign to relevant department.')),
            'duration_ms' => $durationMs,
            'model_used' => (string)($outer['model'] ?? $this->model),
            'provider' => 'ollama',
        ];

        // Store analysis into database if submission_id is available
        $submissionId = (int)($submission['id'] ?? 0);
        if ($submissionId > 0 && function_exists('db')) {
            $this->saveAnalysisRecord($submissionId, 'Triage & Categorization', $clean);
        }

        return $clean;
    }

    /**
     * Generates a formal, courteous official government response draft.
     */
    public function generateResponseDraft(array $submission, string $responseType): array
    {
        $startedAt = microtime(true);
        $citizenName = trim((string)($submission['citizen_name'] ?? 'Citizen'));
        $ref = (string)($submission['reference_number'] ?? 'REF-XXXX');
        $type = (string)($submission['submission_type'] ?? 'Complaint');
        $title = (string)($submission['title'] ?? '');
        $details = (string)($submission['details'] ?? '');
        $category = (string)($submission['category_name'] ?? 'City Services');

        $prompt = <<<PROMPT
You are a Senior Public Communications Officer for the City Government of Manila.
Draft an official, professional, courteous, and empathetic government letter/response for a citizen submission.

CITIZEN DETAILS:
- Name: {$citizenName}
- Reference Number: {$ref}
- Type: {$type}
- Subject: {$title}
- Category: {$category}
- Citizen Message:
{$details}

RESPONSE TYPE REQUIRED: {$responseType}
(Options: "Acknowledgement", "Progress Update", "Clarification Request", "Resolution Notice", "Official Response")

GUIDELINES:
- Keep the tone respectful, official, and reassuring.
- For Acknowledgement: Acknowledge receipt of the {$ref} ticket, mention that it is assigned to the appropriate city department for evaluation, and provide the tracking reference.
- For Clarification Request: Politely request additional details (such as exact street landmarks, house numbers, or photos) needed to take action.
- For Progress Update: Inform the citizen that operations/inspections are actively underway.
- For Resolution Notice: State that the matter has been resolved or appropriate enforcement/repairs have been conducted.
- Write primarily in English with a dignified, public-service standard, signed by "City Government of Manila - Public Assistance and Citizen Engagement Office".

Return ONLY a valid JSON object:
{
  "subject": "<Concise official email/portal subject line>",
  "body": "<Complete official response letter body with greeting and closing>"
}
PROMPT;

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
            'keep_alive' => AI_MODEL_KEEP_ALIVE_MINUTES . 'm',
            'options' => [
                'temperature' => 0.2,
                'num_predict' => 700,
            ],
        ];

        $res = $this->curlRequest('POST', '/api/generate', $payload, $this->connectTimeout, $this->requestTimeout);
        $durationMs = (int)round((microtime(true) - $startedAt) * 1000);

        if (!$res['success']) {
            return [
                'success' => false,
                'error' => $res['error'] ?? 'Ollama connection error.',
                'duration_ms' => $durationMs,
            ];
        }

        $outer = json_decode((string)$res['body'], true);
        $parsed = isset($outer['response']) ? json_decode((string)$outer['response'], true) : null;

        if (!is_array($parsed) || empty($parsed['body'])) {
            return [
                'success' => false,
                'error' => 'Failed to generate structured response draft.',
                'duration_ms' => $durationMs,
            ];
        }

        return [
            'success' => true,
            'subject' => trim((string)($parsed['subject'] ?? "[{$ref}] Official Response - City Government of Manila")),
            'body' => trim((string)$parsed['body']),
            'duration_ms' => $durationMs,
            'model_used' => (string)($outer['model'] ?? $this->model),
        ];
    }

    /**
     * Checks semantic similarity between two submissions.
     */
    public function checkSemanticDuplicate(string $textA, string $textB): array
    {
        $prompt = <<<PROMPT
Compare the following two civic reports/complaints in the City of Manila.
Determine if they describe the SAME incident, location, or issue (even if phrased in different words, Tagalog, or English).

Report A:
{$textA}

Report B:
{$textB}

Return ONLY a JSON object:
{
  "similarity_score": 0-100,
  "is_duplicate": true | false,
  "reasoning": "<Short explanation in one sentence>"
}
PROMPT;

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
            'keep_alive' => AI_MODEL_KEEP_ALIVE_MINUTES . 'm',
            'options' => [
                'temperature' => 0.1,
                'num_predict' => 200,
            ],
        ];

        $res = $this->curlRequest('POST', '/api/generate', $payload, 5, 25);
        if (!$res['success']) {
            return ['success' => false, 'similarity_score' => 0, 'is_duplicate' => false];
        }

        $outer = json_decode((string)$res['body'], true);
        $parsed = isset($outer['response']) ? json_decode((string)$outer['response'], true) : null;

        if (!is_array($parsed)) {
            return ['success' => false, 'similarity_score' => 0, 'is_duplicate' => false];
        }

        return [
            'success' => true,
            'similarity_score' => max(0, min(100, (int)($parsed['similarity_score'] ?? 0))),
            'is_duplicate' => (bool)($parsed['is_duplicate'] ?? false),
            'reasoning' => trim((string)($parsed['reasoning'] ?? '')),
        ];
    }

    /**
     * Generates an official case progress update for a complaint.
     */
    public function generateCaseUpdateDraft(array $submission, string $updateType): array
    {
        $title = (string)($submission['title'] ?? '');
        $details = (string)($submission['details'] ?? '');
        $ref = (string)($submission['reference_number'] ?? 'REF-XXXX');
        $location = (string)($submission['location_text'] ?? $submission['barangay'] ?? 'City of Manila');

        $prompt = <<<PROMPT
You are a City Government of Manila Case Officer managing citizen complaint {$ref}.
Write a formal, concise internal/public case progress update for the official record.

COMPLAINT DETAILS:
- Reference: {$ref}
- Issue: {$title}
- Location: {$location}
- Complaint Message: {$details}
- Update Type: {$updateType}
  (Options: Progress Update, Inspection / Verification, Office Action, Citizen Contact, Service Coordination, Resolution Preparation)

GUIDELINES:
- Keep it factual, professional, and clear (2 to 4 sentences).
- For Inspection: Note that field personnel or technical team was dispatched to assess the site and verify citizen claims.
- For Service Coordination: Note that coordination with utility or engineering crews is active.
- For Office Action: Summarize the concrete operational action taken on the ground.
- For Resolution Preparation: Summarize the completed repair/cleaning and pending final clearance.

Return ONLY a JSON object:
{
  "update_text": "<Formal 2-4 sentence case update message>"
}
PROMPT;

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
            'keep_alive' => AI_MODEL_KEEP_ALIVE_MINUTES . 'm',
            'options' => [
                'temperature' => 0.2,
                'num_predict' => 350,
            ],
        ];

        $res = $this->curlRequest('POST', '/api/generate', $payload, $this->connectTimeout, $this->requestTimeout);
        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error'] ?? 'Ollama connection error.'];
        }

        $outer = json_decode((string)$res['body'], true);
        $parsed = isset($outer['response']) ? json_decode((string)$outer['response'], true) : null;
        if (!is_array($parsed) || empty($parsed['update_text'])) {
            return ['success' => false, 'error' => 'Failed to parse AI update draft.'];
        }

        return [
            'success' => true,
            'update_text' => trim((string)$parsed['update_text']),
            'model_used' => (string)($outer['model'] ?? $this->model),
        ];
    }

    /**
     * Generates an official escalation memorandum reason for a complaint.
     */
    public function generateEscalationDraft(array $submission, string $level): array
    {
        $title = (string)($submission['title'] ?? '');
        $details = (string)($submission['details'] ?? '');
        $ref = (string)($submission['reference_number'] ?? 'REF-XXXX');
        $location = (string)($submission['location_text'] ?? $submission['barangay'] ?? 'City of Manila');

        $prompt = <<<PROMPT
You are an Operations Officer in the City Government of Manila.
Draft an official escalation memorandum justification for elevating citizen complaint {$ref} to management.

COMPLAINT DETAILS:
- Reference: {$ref}
- Issue: {$title}
- Location: {$location}
- Details: {$details}
- Escalation Level: {$level} (e.g. Attention, High, Urgent, Executive Review)

GUIDELINES:
- Provide a clear, authoritative, 2-3 sentence justification explaining why this complaint requires expedited administrative attention or executive inter-agency intervention.
- Emphasize public safety, recurring citizen disruption, and SLA compliance risk.

Return ONLY a JSON object:
{
  "reason": "<Authoritative 2-3 sentence escalation justification>"
}
PROMPT;

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'format' => 'json',
            'keep_alive' => AI_MODEL_KEEP_ALIVE_MINUTES . 'm',
            'options' => [
                'temperature' => 0.2,
                'num_predict' => 350,
            ],
        ];

        $res = $this->curlRequest('POST', '/api/generate', $payload, $this->connectTimeout, $this->requestTimeout);
        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error'] ?? 'Ollama connection error.'];
        }

        $outer = json_decode((string)$res['body'], true);
        $parsed = isset($outer['response']) ? json_decode((string)$outer['response'], true) : null;
        if (!is_array($parsed) || empty($parsed['reason'])) {
            return ['success' => false, 'error' => 'Failed to parse AI escalation reason.'];
        }

        return [
            'success' => true,
            'reason' => trim((string)$parsed['reason']),
            'model_used' => (string)($outer['model'] ?? $this->model),
        ];
    }

    /**
     * Save AI analysis to `cef_ai_analysis` table.
     */
    private function saveAnalysisRecord(int $submissionId, string $analysisType, array $data): void
    {
        try {
            $pdo = db();
            $stmt = $pdo->prepare(
                'INSERT INTO cef_ai_analysis 
                 (submission_id, analysis_type, provider, model_used, result_json, confidence_score, review_status, created_at)
                 VALUES (:sub_id, :type, :provider, :model, :json, :conf, "Reviewed", NOW())'
            );
            $stmt->execute([
                ':sub_id' => $submissionId,
                ':type' => $analysisType,
                ':provider' => $data['provider'] ?? 'ollama',
                ':model' => $data['model_used'] ?? $this->model,
                ':json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ':conf' => ($data['confidence_score'] ?? 75) / 100.0,
            ]);
        } catch (Throwable $e) {
            $this->logError('Failed to save cef_ai_analysis: ' . $e->getMessage());
        }
    }

    /**
     * cURL request helper.
     */
    private function curlRequest(string $method, string $path, ?array $payload, int $connectTimeout, int $requestTimeout): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $requestTimeout);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $json = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen((string)$json),
            ]);
        }

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'success' => false,
                'error' => $error ?: 'cURL connection error (' . $errno . ')',
                'http_status' => null,
                'body' => null,
            ];
        }

        return [
            'success' => true,
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }

    private function logError(string $message): void
    {
        if (defined('AI_LOG_FILE')) {
            $dir = dirname(AI_LOG_FILE);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            @file_put_contents(AI_LOG_FILE, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
        }
    }
}
