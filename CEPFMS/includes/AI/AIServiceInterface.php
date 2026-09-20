<?php
declare(strict_types=1);

/**
 * includes/AI/AIServiceInterface.php
 * Interface for AI service providers in CEPFMS.
 */
interface AIServiceInterface
{
    /**
     * Diagnostic health check of the AI daemon and installed model.
     * Returns ['available' => bool, 'message' => string, 'models' => array].
     */
    public function healthCheck(): array;

    /**
     * Comprehensive multi-factor analysis of a citizen submission.
     * Analyzes sentiment, urgency level & score, civic category suggestion,
     * safety/profanity flags, actionability, and executive summary.
     */
    public function analyzeSubmission(array $submission): array;

    /**
     * Generates a formal, courteous official government response draft.
     * Suitable for Acknowledgement, Clarification Request, Resolution Notice, etc.
     */
    public function generateResponseDraft(array $submission, string $responseType): array;

    /**
     * Compares two submissions semantically to evaluate duplicate likelihood (0-100).
     */
    public function checkSemanticDuplicate(string $textA, string $textB): array;

    /**
     * Generates a formal complaint case update summary based on update type.
     */
    public function generateCaseUpdateDraft(array $submission, string $updateType): array;

    /**
     * Generates an official escalation memorandum reason for a complaint.
     */
    public function generateEscalationDraft(array $submission, string $level): array;
}

