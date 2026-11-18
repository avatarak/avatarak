<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV downloads for the administrator. Every export is written to the audit log. */
class ExportController extends Controller
{
    public const KINDS = ['users', 'reports', 'audit', 'payments'];

    /** A cell that starts with = + - @ (or a tab/CR) is run as a formula by spreadsheets: neutralise it. */
    public static function cell(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $v = (string) $v;

        return $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'".$v : $v;
    }

    public function download(Request $request, string $kind): StreamedResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        [$header, $rows] = $this->{'rows'.ucfirst($kind)}($request);
        $count = 0;
        AuditLog::record('export.'.$kind);

        return response()->streamDownload(function () use ($header, $rows, &$count) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");                                  // BOM so Excel reads Persian text as UTF-8
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows() as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), ',', '"', '');
                $count++;
            }
            fclose($out);
        }, "avatarak-{$kind}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function rowsUsers(Request $request): array
    {
        $plans = app(PlanService::class);

        return [['id', 'name', 'email', 'username', 'plan', 'is_admin', 'suspended', 'created_at', 'last_login_at'], function () use ($plans) {
            foreach (User::with('profile')->orderBy('id')->lazyById(500) as $u) {
                yield [$u->id, $u->name, $u->email, $u->profile?->username, $plans->planKey($u), (bool) $u->is_admin, $u->isSuspended(), $u->created_at?->toIso8601String(), $u->last_login_at?->toIso8601String()];
            }
        }];
    }

    private function rowsReports(Request $request): array
    {
        return [['id', 'profile', 'reason', 'status', 'details', 'created_at', 'handled_at', 'handled_by', 'note'], function () {
            foreach (Report::with(['profile', 'handler'])->orderBy('id')->lazyById(500) as $r) {
                yield [$r->id, $r->profile?->username, $r->reason, $r->status, $r->details, $r->created_at?->toIso8601String(), $r->handled_at?->toIso8601String(), $r->handler?->email, $r->note];
            }
        }];
    }

    private function rowsAudit(Request $request): array
    {
        return [['id', 'at', 'admin', 'action', 'target_type', 'target_id', 'target', 'meta'], function () {
            foreach (AuditLog::orderBy('id')->lazyById(500) as $l) {
                yield [$l->id, $l->created_at?->toIso8601String(), $l->admin_label, $l->action, $l->target_type, $l->target_id, $l->target_label, $l->meta];
            }
        }];
    }

    private function rowsPayments(Request $request): array
    {
        $status = in_array($s = (string) $request->query('status'), ['pending', 'paid', 'failed'], true) ? $s : null;

        return [['id', 'email', 'plan', 'period', 'amount', 'currency', 'gateway', 'status', 'ref_id', 'paid_at', 'created_at'], function () use ($status) {
            foreach (Payment::with('user')->when($status, fn ($q) => $q->where('status', $status))->orderBy('id')->lazyById(500) as $p) {
                yield [$p->id, $p->user?->email, $p->plan, $p->period, $p->amount, $p->currency, $p->gateway, $p->status, $p->ref_id, $p->paid_at?->toIso8601String(), $p->created_at?->toIso8601String()];
            }
        }];
    }
}
