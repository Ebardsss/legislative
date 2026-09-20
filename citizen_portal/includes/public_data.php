<?php
declare(strict_types=1);

require_once __DIR__.'/auth.php';

function publicModulePagination(int $total,int $page,int $perPage=12): array
{
    $perPage=max(1,min(50,$perPage));
    $pages=max(1,(int)ceil(max(0,$total)/$perPage));
    $page=max(1,min($pages,$page));

    return [
        'total'=>$total,
        'page'=>$page,
        'perPage'=>$perPage,
        'pages'=>$pages,
        'offset'=>($page-1)*$perPage,
    ];
}

function publicModulePaginationHtml(array $pageInfo,string $basePath,array $query=[]): string
{
    if(($pageInfo['pages']??1)<=1)return '';

    $current=(int)$pageInfo['page'];
    $pages=(int)$pageInfo['pages'];
    $start=max(1,$current-2);
    $end=min($pages,$current+2);

    $url=static function(int $page) use($basePath,$query): string {
        $params=array_filter(
            array_merge($query,['page'=>$page]),
            static fn(mixed $v): bool => $v!==null && $v!==''
        );
        return appUrl($basePath).'?'.http_build_query($params);
    };

    $html='<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';
    $html.='<li class="page-item '.($current<=1?'disabled':'').'"><a class="page-link" href="'.
        e($url(max(1,$current-1))).'">Previous</a></li>';

    for($i=$start;$i<=$end;$i++){
        $html.='<li class="page-item '.($i===$current?'active':'').'"><a class="page-link" href="'.
            e($url($i)).'">'.$i.'</a></li>';
    }

    $html.='<li class="page-item '.($current>=$pages?'disabled':'').'"><a class="page-link" href="'.
        e($url(min($pages,$current+1))).'">Next</a></li>';
    $html.='</ul></nav>';

    return $html;
}

function portalStatusClass(string $status): string
{
    $s=strtolower(trim($status));

    if(in_array($s,[
        'published','finalized','archived','completed','approved',
        'enacted','passed','adopted','confirmed','valid','certified'
    ],true))return 'good';

    if(in_array($s,[
        'in progress','ongoing','upcoming','scheduled','under review'
    ],true))return 'warn';

    if(in_array($s,[
        'rejected','cancelled','failed','invalid','vetoed'
    ],true))return 'bad';

    return 'neutral';
}

function portalExcerpt(?string $text,int $length=180): string
{
    $text=trim(strip_tags((string)$text));
    if($text==='')return 'No public summary available.';
    if(mb_strlen($text)<=$length)return $text;
    return rtrim(mb_substr($text,0,$length-1)).'…';
}

function portalPublicUrl(?string $url): ?string
{
    $url=trim((string)$url);
    if($url==='' || !filter_var($url,FILTER_VALIDATE_URL))return null;
    $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
    return in_array($scheme,['http','https'],true)?$url:null;
}

function portalLogPublicView(string $module,string $reference): void
{
    portalLog(
        currentUserId(),
        'Citizen Portal Public View',
        $module.' · '.$reference
    );
}
