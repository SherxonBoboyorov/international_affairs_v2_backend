<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;">
        <h2>Parolni tiklash</h2>
    </div>

    <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px;">
        <p>Hurmatli {{ $user->name }},</p>

        <p>Siz parolni tiklash so'rovini yubordingiz. Tasdiqlash kodi:</p>

        <div style="background: #28a745; color: white; font-size: 24px; font-weight: bold;
                    padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;">
            {{ $code }}
        </div>
    </div>
</div>
