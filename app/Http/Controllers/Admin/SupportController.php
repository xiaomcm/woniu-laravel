<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Response as HttpResponse;
use App\Http\Controllers\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Feedback;
use App\Models\Report;
use App\Models\Ticket;

class SupportController extends Controller
{
    public function tickets(): HttpResponse
    {
        Auth::requireAdmin();

        $page = max(1, Request::int('page', 1));
        $status = Request::input('status', '');
        $keyword = trim((string) Request::input('keyword', ''));

        $result = Ticket::paginate([
            'status'  => $status === '' ? null : (int) $status,
            'keyword' => $keyword,
        ], $page, 20);

        return $this->view('admin/tickets', [
            'pageTitle' => '工单',
            'tickets'   => $result['items'],
            'pager'     => $result,
            'status'    => $status,
            'keyword'   => $keyword,
            'openCount' => Ticket::countOpen(),
        ], 'layouts/admin');
    }

    public function ticketShow(string $id): HttpResponse
    {
        $id = (int) $id;
        Auth::requireAdmin();

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            abort(404, '工单不存在');
        }

        return $this->view('admin/ticket_show', [
            'pageTitle' => '工单 #' . $id,
            'ticket'    => $ticket,
            'replies'   => Ticket::replies($id),
            'categories' => Ticket::categories(),
        ], 'layouts/admin');
    }

    public function ticketReply(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            abort(404, '工单不存在');
        }

        $content = trim((string) Request::input('content', ''));
        if ($content === '') {
            flash('error', '回复内容不能为空');
            back();
        }

        Ticket::reply($id, (int) Auth::id(), true, $content);
        flash('success', '回复已发送');
        redirect('admin/support/tickets/' . $id);
    }

    public function ticketClose(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        $ticket = Ticket::find($id);
        if ($ticket === null) {
            abort(404, '工单不存在');
        }

        if ((int) $ticket['status'] === Ticket::STATUS_CLOSED) {
            Ticket::reopen($id);
        } else {
            Ticket::close($id);
        }

        flash('success', '工单状态已更新');
        redirect('admin/support/tickets/' . $id);
    }

    public function ticketDelete(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        if (Ticket::find($id) === null) {
            abort(404, '工单不存在');
        }

        Ticket::deleteById($id);
        flash('success', '工单已删除');
        redirect('admin/support/tickets');
    }

    public function reports(): HttpResponse
    {
        Auth::requireAdmin();

        $page = max(1, Request::int('page', 1));
        $status = Request::input('status', '');

        $result = Report::paginate([
            'status' => $status === '' ? null : (int) $status,
        ], $page, 20);

        return $this->view('admin/reports', [
            'pageTitle'  => '举报处理',
            'reports'    => $result['items'],
            'pager'      => $result,
            'status'     => $status,
            'pending'    => Report::countPending(),
            'categories' => Report::categories(),
        ], 'layouts/admin');
    }

    public function reportRedirect(): void
    {
        // 兜底：旧缓存页面里弹窗 action 为空时会 POST 到这里，别让它落到 404
        Auth::requireAdmin();
        flash('error', '请点击列表里对应记录的「处理」按钮再提交');
        redirect('admin/support/reports');
    }

    public function reportHandle(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        $report = Report::find($id);
        if ($report === null) {
            abort(404, '举报记录不存在');
        }

        $decision = (string) Request::input('decision', 'handle');
        $note = (string) Request::input('note', '');

        if ($decision === 'reject') {
            Report::handle($id, Report::STATUS_REJECTED, (int) Auth::id(), $note);
            flash('success', '举报已驳回');
            redirect('admin/support/reports');
        }

        Report::handle($id, Report::STATUS_HANDLED, (int) Auth::id(), $note);

        if (!empty($report['image_id']) && Request::bool('offline', false)) {
            \App\Core\Database::update('images', [
                'status'      => 2,
                'review_note' => '举报下线：' . mb_substr($note, 0, 120),
                'updated_at'  => now(),
            ], 'id = :id', ['id' => (int) $report['image_id']]);
        }

        flash('success', '举报已处理');
        redirect('admin/support/reports');
    }

    public function reportDelete(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        if (Report::find($id) === null) {
            abort(404, '举报记录不存在');
        }

        Report::deleteById($id);
        flash('success', '举报记录已删除');
        redirect('admin/support/reports');
    }

    public function feedbacks(): HttpResponse
    {
        Auth::requireAdmin();

        $page = max(1, Request::int('page', 1));
        $status = Request::input('status', '');

        $result = Feedback::paginate([
            'status' => $status === '' ? null : (int) $status,
        ], $page, 20);

        return $this->view('admin/feedbacks', [
            'pageTitle' => '意见收集',
            'feedbacks' => $result['items'],
            'pager'     => $result,
            'status'    => $status,
            'newCount'  => Feedback::countNew(),
            'kinds'     => Feedback::kinds(),
        ], 'layouts/admin');
    }

    public function feedbackReply(string $id): void
    {
        $id = (int) $id;
        Auth::requireAdmin();
        $this->validateCsrf();

        $feedback = Feedback::find($id);
        if ($feedback === null) {
            abort(404, '意见不存在');
        }

        $reply = trim((string) Request::input('reply', ''));
        if ($reply === '') {
            Feedback::markRead($id);
            flash('success', '已标记为已读');
            redirect('admin/support/feedbacks');
        }

        Feedback::reply($id, $reply);
        flash('success', '回复已保存');
        redirect('admin/support/feedbacks');
    }
}
