<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
?>
<div class="page-sheet">
    <div class="page-sheet-head">
        <h1 class="page-sheet-title">{{ (string) $page['title'] }}</h1>
        <div class="hint">更新于 {{ (string) ($page['updated_at'] ?? $page['created_at'] ?? '') }}</div>
    </div>

    <article class="prose">
        {!! (string) $page['content'] !!}    </article>

    <div class="page-sheet-foot">
        <a class="btn btn-sm" href="javascript:history.back()">返回上一页</a>
        <div class="spacer" style="flex:1"></div>
        @foreach ($siblings as $item)
            <?php if ((string) $item['slug'] === (string) $page['slug']) { continue; } ?>
            <a class="btn btn-sm btn-ghost" href="{{ url('p/' . (string) $item['slug']) }}">{{ (string) $item['title'] }}</a>
        @endforeach
    </div>
</div>
