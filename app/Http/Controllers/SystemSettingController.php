<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(): View
    {
        return view('settings.index', ['settings' => [
            'duplicate_detection_fields' => SystemSetting::value('duplicate_detection_fields', config('vaccination.duplicate_detection_fields')),
            'duplicate_review_minutes' => SystemSetting::value('duplicate_review_minutes', config('vaccination.duplicate_review_minutes')),
            'near_expiry_days' => SystemSetting::value('near_expiry_days', 30),
        ]]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'duplicate_detection_fields' => ['required', 'array', 'min:1'],
            'duplicate_detection_fields.*' => ['required', 'distinct', Rule::in(['name', 'date_of_birth', 'guardian_phone', 'registration_id'])],
            'duplicate_review_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'near_expiry_days' => ['required', 'integer', Rule::in([7, 14, 30, 60, 90])],
        ]);
        if (in_array('name', $data['duplicate_detection_fields'], true) !== in_array('date_of_birth', $data['duplicate_detection_fields'], true)) {
            return back()->withInput()->withErrors(['duplicate_detection_fields' => 'Name and date of birth must be enabled together.']);
        }

        $before = [
            'duplicate_detection_fields' => SystemSetting::value('duplicate_detection_fields', config('vaccination.duplicate_detection_fields')),
            'duplicate_review_minutes' => SystemSetting::value('duplicate_review_minutes', config('vaccination.duplicate_review_minutes')),
            'near_expiry_days' => SystemSetting::value('near_expiry_days', 30),
        ];
        DB::transaction(function () use ($request, $data, $before): void {
            foreach ($data as $key => $value) {
                SystemSetting::saveValue($key, $value, $request->user()->id);
            }
            AuditLog::record($request, 'settings.updated', null, $before, $data);
        });

        return redirect()->route('settings.index')->with('status', 'System settings updated.');
    }
}
