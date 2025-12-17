<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmailSignatureController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        // 1. تحميل العلاقات (الموظف والشركة)
        $user = $request->user()->load('employee.company');

        // استخراج بيانات الموظف (لو موجود)
        $employee = $user->employee;
        $company = $employee ? $employee->company : null;

        // 2. تجهيز البيانات
        $fullName = $user->fName . ' ' . $user->lName;
        
        // البيانات الوظيفية بتيجي من جدول employees
        $position = $employee && $employee->position ? $employee->position : 'Member';
        $companyName = $company ? $company->name : 'iTap';
        $website = $company && $company->website ? $company->website : 'www.itap.qa';
        
        $phone = $user->phone_num ?? '';
        $email = $user->email;

        // 3. الروابط المهمة
        // هل البروفايل على api ولا على الروت الرئيسي؟ غالباً بيكون url('/' . slug)
        $profileUrl = url('/' . ($user->profile_url_slug ?? $user->id)); 
        $vcardUrl = url('/api/vcard/' . ($user->profile_url_slug ?? $user->id));

        // 4. معالجة الصور (أهم نقطة للإيميل) 🖼️
        if ($user->profile_image) {
            // تحويل المسار النسبي لرابط كامل (Absolute URL)
            $avatar = asset('storage/' . $user->profile_image);
        } else {
            $avatar = 'https://ui-avatars.com/api/?background=0D8ABC&color=fff&rounded=true&name=' . urlencode($fullName);
        }
        
        // رابط QR Code
        $qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($profileUrl);

        // 5. بناء القالب (HTML Email Signature)
        $htmlSignature = '
        <table cellpadding="0" cellspacing="0" border="0" style="font-family: Arial, sans-serif; font-size: 14px; color: #333; line-height: 1.5; width: auto;">
            <tr>
                <td valign="top" style="padding-right: 15px;">
                    <img src="' . $avatar . '" alt="' . $fullName . '" style="width: 65px; height: 65px; border-radius: 50%; object-fit: cover; display: block;">
                </td>
                
                <td valign="top" style="border-left: 2px solid #e2e8f0; padding-left: 15px;">
                    <strong style="font-size: 16px; color: #000; display: block; margin-bottom: 2px;">' . $fullName . '</strong>
                    <span style="color: #64748b; font-size: 13px; display: block; margin-bottom: 8px;">' . $position . ' at ' . $companyName . '</span>
                    
                    <div style="font-size: 12px; line-height: 1.8;">
                        ' . ($phone ? '<a href="tel:' . $phone . '" style="text-decoration: none; color: #333; display: block;">📞 ' . $phone . '</a>' : '') . '
                        <a href="mailto:' . $email . '" style="text-decoration: none; color: #333; display: block;">✉️ ' . $email . '</a>
                        <a href="' . $website . '" style="text-decoration: none; color: #333; display: block;">🌐 ' . $website . '</a>
                    </div>

                    <div style="margin-top: 10px;">
                        <a href="' . $vcardUrl . '" style="background-color: #000; color: #fff; padding: 6px 10px; text-decoration: none; border-radius: 4px; font-size: 11px; display: inline-block; margin-right: 5px;">Save Contact</a>
                        <a href="' . $profileUrl . '" style="border: 1px solid #000; color: #000; padding: 5px 9px; text-decoration: none; border-radius: 4px; font-size: 11px; display: inline-block;">View Profile</a>
                    </div>
                </td>

                <td valign="middle" style="padding-left: 15px;">
                    <img src="' . $qrImage . '" alt="Scan Profile" style="width: 70px; height: 70px; display: block;">
                </td>
            </tr>
        </table>';

        return response()->json([
            'message' => 'Signature generated successfully',
            'html' => trim(preg_replace('/\s+/', ' ', $htmlSignature)), // Minify HTML
            'preview_data' => [
                'full_name' => $fullName,
                'avatar_url' => $avatar,
                'position' => $position
            ]
        ]);
    }
}