<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */

$maxSize = $maxSize ?? 5242880;
$maxNum  = (int) ($maxNum ?? 0);
$allowed = $allowed ?? '';
$albums = $albums ?? [];
$storages = $storages ?? [];
?>
<div data-uploader
     data-endpoint="{{ url('upload') }}"
     data-token="{{ csrf_token() }}"
     data-max-size="{!! (int) $maxSize !!}"
     data-max-num="{!! $maxNum !!}"
     data-allowed="{{ $allowed }}">

    <div class="dropzone" data-dropzone>
        <div class="dz-icon">
            <svg class="ic ic-lg" style="width:26px;height:26px"><use href="#i-upload"/></svg>
        </div>
        <div class="dz-title">点击选择、拖拽到此处，或直接 <kbd>Ctrl</kbd> + <kbd>V</kbd> 粘贴</div>
        <div class="dz-sub">
            支持 {{ $allowed !== '' ? $allowed : 'jpg,png,gif,webp' }}，
            单张最大 {{ format_bytes($maxSize) }}，
            @if ($maxNum > 0)单次最多 {{ $maxNum }} 张@else支持多选@endif
        </div>
    </div>
    <input type="file" data-file-input multiple accept="image/*" hidden>

    <div class="toolbar" style="margin:14px 0 0">
        <select class="select" style="width:auto;min-width:150px" name="album_id">
            <option value="">未分类</option>
            @foreach ($albums as $album)
                <option value="{!! (int) $album['id'] !!}">{{ (string) $album['name'] }}</option>
            @endforeach
        </select>

        @if (count($storages) > 1)
            <select class="select" style="width:auto;min-width:150px" name="storage_id">
                <option value="">默认存储</option>
                @foreach ($storages as $storage)
                    <option value="{!! (int) $storage['id'] !!}">{{ (string) $storage['name'] }}</option>
                @endforeach
            </select>
        @else
            <input type="hidden" name="storage_id" value="">
        @endif

        <div class="spacer"></div>

        <form data-url-form style="display:flex;gap:8px;align-items:center">
            <input class="input input-sm" style="width:230px" name="url" placeholder="粘贴远程图片地址…" autocomplete="off">
            <button class="btn btn-sm" type="submit">
                <svg class="ic" style="width:15px;height:15px"><use href="#i-link"/></svg> 拉取
            </button>
        </form>
    </div>

    <div class="upload-queue" data-queue></div>
    <div class="result-grid" data-results></div>
</div>
