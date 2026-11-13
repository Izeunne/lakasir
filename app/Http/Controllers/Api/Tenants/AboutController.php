<?php

namespace App\Http\Controllers\Api\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Resources\AboutResource;
use App\Models\Tenants\About;
use App\Models\Tenants\Setting;
use App\Services\Tenants\AboutService;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function index()
    {
        return $this->buildResponse()
            ->setData(new AboutResource(About::first()))
            ->present();
    }

    public function update(Request $request, AboutService $aboutService)
    {
        $this->validate($request, [
            'shop_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'shop_location' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'business_type' => ['required', 'in:retail,wholesale,fnb,fashion,pharmacy,other'],
            'other_business_type' => ['required_if:business_type,other'],
            'owner_name' => ['nullable', 'string'],
            'uploaded_file_id' => ['nullable', 'integer', 'exists:uploaded_files,id'],
        ]);

        $data = $request->all();
        if (isset($data['company_name']) && !isset($data['shop_name'])) {
            $data['shop_name'] = $data['company_name'];
        }
        if (isset($data['address']) && !isset($data['shop_location'])) {
            $data['shop_location'] = $data['address'];
        }

        $aboutService->createOrUpdate($data);

        Setting::set('currency', $request->currency ?? 'IDR');

        return $this->buildResponse()
            ->setMessage('About updated successfully')
            ->present();
    }
}