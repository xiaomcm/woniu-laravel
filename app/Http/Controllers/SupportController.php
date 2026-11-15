<?php
/**
 * 蜗牛图床 WONIU
 * 作者 QQ：34995549
 * 官网：www.zhujitown.com
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Setting;
use App\Models\Feedback;
use App\Models\Image;
use App\Models\Report;
use App\Models\Ticket;

class SupportController extends Controller
{
    public function tickets(): HttpResponse
    {
        $user = Auth::requireLogin();

        $page = max(1, Request::int('page', 1));
        $result = Ticket::paginate(['user_id' => (int) $user['id']], $page, 15);

        return $this->view('tickets', [
            'pageTitle' => '我的工单',
            'tickets'   => $result['items'],
            'pager'     => $result,
            'enabled'   => Setting::bool('ticket_enabled', true),
        ], 'layouts/app');
    }

    public function ticketCreate(): HttpResponse
    {
        Auth::requireLogin();

        if (!Setting::bool('ticket_enabled', true)) {
            abort(404, '工单未开放');
        }

        return $this->view('ticket_new', [
            'pageTitle'  => '提交工单',
            'categories' => Ticket::categories(),
        ], 'layouts/app');
    }

    public function ticketStore(): void
    {
        $user = Auth::requireLogin();
        $this->validateCsrf();

        if (!Setting::bool('ticket_enabled', true)) {
            abort(404, '工单未开放');
        }

        $subject = trim((string) Request::input('subject', ''));
        $content = trim((string) Request::input('content', ''));
        if (mb_strlen($subject) < 4) {
            flash('error', '标题至少 4 个字');
            back();
        }
        if (mb_strlen($content) < 10) {
            flash('error', '问题描述至少 10 个字，方便我们定位');
            back();
        }

        $id = Ticket::create(
            (int) $user['id'],
            $subject,
            (string) Request::input('category', 'other'),
            Request::int('priority', 1),
            $content
        );

        flash('success', '工单已提交，我们会尽快回复');
        redirect('support/tickets/' . $id);
    }

    public function ticketShow(string $id): HttpResponse
    {
        $id = (int) $id;
        $user = Auth::requireLogin();

        $ticket = Ticket::findForUser($id, (int) $user['id']);
        if ($ticket === null) {
            abort(404, '工单不存在');
        }

        return $this->view('ticket_show', [
            'pageTitle'  => '工单 #' . $id,
            'ticket'     => $ticket,
            'replies'    => Ticket::replies($id),
            'categories' => Ticket::categories(),
        ], 'layouts/app');
    }

    public function ticketReply(string $id): void
    {
        $id = (int) $id;
        $user = Auth::requireLogin();
        $this->validateCsrf();

        $ticket = Ticket::findForUser($id, (int) $user['id']);
        if ($ticket === null) {
            abort(404, '工单不存在');
        }
        if ((int) $ticket['status'] === Ticket::STATUS_CLOSED) {
            flash('error', '工单已关闭，如需继续请新建工单');
            back();
        }

        $content = trim((string) Request::input('content', ''));
        if ($content === '') {
            flash('error', '内容不能为空');
            back();
        }

        Ticket::reply($id, (int) $user['id'], false, $content);
        flash('success', '已发送');
        redirect('support/tickets/' . $id);
    }

    public function ticketDelete(string $id): void
    {
        $id = (int) $id;
        $user = Auth::requireLogin();
        $this->validateCsrf();

        if (Ticket::findForUser($id, (int) $user['id']) === null) {
            abort(404, '工单不存在');
        }

        Ticket::deleteById($id);
        flash('success', '工单已删除');
        redirect('support/tickets');
    }

    public function feedback(): HttpResponse
    {
        if (!Setting::bool('feedback_enabled', true)) {
            abort(404, '意见反馈未开放');
        }

        return $this->view('feedback', [
            'pageTitle' => '意见反馈',
            'kinds'     => Feedback::kinds(),
            'user'      => Auth::user(),
        ], 'layouts/site');
    }

    public function feedbackStore(): void
    {
        $this->validateCsrf();

        if (!Setting::bool('feedback_enabled', true)) {
            abort(404, '意见反馈未开放');
        }

        $content = trim((string) Request::input('content', ''));
        if (mb_strlen($content) < 5) {
            flash('error', '内容太短了，多写几个字吧');
            back();
        }

        Feedback::create([
            'user_id' => Auth::id(),
            'kind'    => (string) Request::input('kind', 'suggestion'),
            'content' => mb_substr($content, 0, 2000),
            'contact' => mb_substr((string) Request::input('contact', ''), 0, 120),
        ]);

        flash('success', '感谢反馈，我们会认真看');
        back();
    }

    public function report(): void
    {
        $this->validateCsrf();

        if (!Setting::bool('report_enabled', true)) {
            $this->api(false, '举报通道未开放');
        }

        $imageId = Request::int('image_id', 0);
        $albumId = Request::int('album_id', 0);
        $category = (string) Request::input('category', 'other');
        $reason = trim((string) Request::input('reason', ''));

        if ($imageId <= 0 && $albumId <= 0) {
            $this->api(false, '请指定要举报的内容');
        }
        if ($imageId > 0 && Image::find($imageId) === null) {
            $this->api(false, '图片不存在');
        }
        if (!array_key_exists($category, Report::categories())) {
            $category = 'other';
        }

        $key = 'report_' . $imageId . '_' . $albumId . '_' . (Auth::id() ?? Request::ip());
        if (Session::has($key) && time() - (int) Session::get($key) < 60) {
            $this->api(false, '刚提交过举报，请稍后再试');
        }
        Session::put($key, time());

        Report::create([
            'image_id'    => $imageId > 0 ? $imageId : null,
            'album_id'    => $albumId > 0 ? $albumId : null,
            'reporter_id' => Auth::id(),
            'category'    => $category,
            'reason'      => mb_substr($reason, 0, 480),
        ]);

        $this->api(true, '举报已提交，管理员会尽快核实');
    }
}
