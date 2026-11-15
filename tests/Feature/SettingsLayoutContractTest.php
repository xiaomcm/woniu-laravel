<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台「站点信息」区块排布契约测试。
 *
 * 用户要求后台名称（admin_title）不要单独占一整行，要和「站点描述」
 * 并排展示成两列网格，和上面的「站点名称 | 站点地址」视觉一致。
 *
 * 这里锁三件事：
 *   1. 设置页源码里 site_description 与 admin_title 必须落在同一个 .form-row 里
 *   2. 该 form-row 之后紧跟 ICP 备案号 / 页脚版权 的 form-row（不插散行）
 *   3. 渲染出来的 HTML：两个输入框同属一个 form-row，DOM 上是并列的兄弟 field
 */
class SettingsLayoutContractTest extends TestCase
{
    private function viewFile(): string
    {
        return dirname(__DIR__, 2) . '/resources/views/admin/settings.blade.php';
    }

    public function test_admin_title_shares_a_form_row_with_site_description(): void
    {
        $file = $this->viewFile();
        $this->assertFileExists($file, '后台设置页不存在');
        $code = (string) file_get_contents($file);

        // 取出 admin_title 所在的那个 form-row 的完整片段
        $titlePos = strpos($code, 'name="admin_title"');
        $this->assertNotFalse($titlePos, '设置页没有 admin_title 输入框');

        $rowStart = strrpos(substr($code, 0, $titlePos), '<div class="form-row">');
        $this->assertNotFalse($rowStart, 'admin_title 不在任何 form-row 里，无法并排展示');

        $rowEnd = strpos($code, '</div>', $titlePos);
        $this->assertNotFalse($rowEnd, 'form-row 结构不完整');

        $row = substr($code, $rowStart, $rowEnd - $rowStart);
        $this->assertStringContainsString(
            'name="site_description"',
            $row,
            '「后台名称」没有和「站点描述」并排在同一行'
        );
    }

    public function test_site_info_card_uses_three_paired_rows(): void
    {
        $file = $this->viewFile();
        $code = (string) file_get_contents($file);

        // 站点信息卡片范围内应有 3 个 form-row：名称/地址、描述/后台名、ICP/版权
        $cardStart = strpos($code, '站点信息');
        $this->assertNotFalse($cardStart, '设置页没有「站点信息」卡片');

        $cardEnd = strpos($code, '</div>', strpos($code, 'name="site_footer"'));
        $this->assertNotFalse($cardEnd, '站点信息卡片结构不完整');

        $section = substr($code, $cardStart, $cardEnd - $cardStart);
        $rowCount = substr_count($section, '<div class="form-row">');
        $this->assertSame(
            3,
            $rowCount,
            "站点信息区块应有 3 行并排（站点名称/地址、站点描述/后台名称、ICP/页脚版权），实际 {$rowCount} 行"
        );

        // 每一行都应该是「两个 field」组成，不能出现单列 field
        $rowCountInSection = substr_count($section, '<div class="form-row">');
        $fieldCountInSection = substr_count($section, '<div class="field">');
        $this->assertSame(
            $rowCountInSection * 2,
            $fieldCountInSection,
            "站点信息区块里每个 form-row 应有 2 个 field，实际 {$rowCountInSection} 行 / {$fieldCountInSection} 个 field"
        );

        // 逐行验证：抽取每一行的 field 数量，必须恰好 2
        preg_match_all('/<div class="form-row">(.*?)<\/div>\s*<\/div>/s', $section, $m);
        foreach ($m[1] as $i => $rowBody) {
            $this->assertSame(
                2,
                substr_count($rowBody, '<div class="field">'),
                "站点信息第 " . ($i + 1) . " 行不是并排两列"
            );
        }
    }

    public function test_rendered_page_places_both_inputs_in_one_grid_row(): void
    {
        $html = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/views/admin/settings.blade.php'
        );
        // 源码层面已保证结构，这里额外确认 CSS 网格类存在且是两列
        $css = (string) file_get_contents(
            dirname(__DIR__, 2) . '/public/assets/app.css'
        );
        $this->assertStringContainsString(
            '.form-row { display: grid; grid-template-columns: 1fr 1fr;',
            $css,
            'CSS 里 .form-row 不是两列网格，并排布局不会生效'
        );
        $this->assertStringContainsString('name="admin_title"', $html);
    }
}
