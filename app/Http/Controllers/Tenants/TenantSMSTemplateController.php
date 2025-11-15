<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\TenantSMSTemplate;
use Inertia\Inertia;



class TenantSMSTemplateController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('perPage', 10);

        $defaultTemplates = [
            [
                'name' => 'Internet Expiry',
                'content' => 'Hello {full_name}, your internet subscription for account {account_number} and package {package} will expire on {expiry_date}. Please renew to continue enjoying our service. Contact: {phone}',
            ],
            [
                'name' => 'Renewal Confirmation',
                'content' => 'Hi {full_name}, thank you for renewing your internet subscription (account: {account_number}, package: {package}). Your new expiry date is {expiry_date}.',
            ],
            [
                'name' => 'Welcome Message',
                'content' => 'Welcome {full_name}! Your account ({account_number}) is now active. Username: {username}, Password: {password}, Package: {package}, Phone: {phone}',
            ],
        ];

        foreach ($defaultTemplates as $tpl) {
            $existing = TenantSMSTemplate::where('name', $tpl['name'])->first();
            if (!$existing) {
                TenantSMSTemplate::create($tpl);
            }
        }

        $templates = TenantSMSTemplate::latest()->paginate($perPage)->withQueryString();

        return Inertia::render('SMSTemplates/Index', [
            'templates' => $templates,
            'perPage' => (int) $perPage,
        ]);
    }

    public function create()
    {
        return Inertia::render('SMSTemplates/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:1000',
        ]);

        TenantSMSTemplate::create($validated);

        return redirect()->route('smstemplates.index')->with('success', 'SMS Template created successfully.');
    }

    public function edit(TenantSMSTemplate $smstemplate)
    {
        return Inertia::render('SMSTemplates/Edit', [
            'template' => $smstemplate,
        ]);
    }
    public function update(Request $request, TenantSMSTemplate $smstemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:1000',
        ]);

        $smstemplate->update($validated);

        return redirect()->route('smstemplates.index')->with('success', 'SMS Template updated successfully.');
    }

    public function destroy(TenantSMSTemplate $smstemplate)
    {
        $smstemplate->delete();

        return redirect()->route('smstemplates.index')->with('success', 'SMS Template deleted successfully.');
    }
}
