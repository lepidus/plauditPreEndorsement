<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use APP\plugins\generic\plauditPreEndorsement\classes\api\APIKeyEncryption;
use Firebase\JWT\JWT;

class EncryptLegacyCredentials extends Migration
{
    private const PLUGIN_NAME_SETTINGS = 'plauditpreendorsementplugin';
    private const PLUGIN_CREDENTIALS_SETTINGS = [
        'orcidClientId',
        'orcidClientSecret',
        'plauditAPISecret'
    ];

    public function up(): void
    {
        $credentialSettings = $this->getCredentialSettings();

        if (!empty($credentialSettings)) {
            $encrypter = new APIKeyEncryption();

            foreach ($credentialSettings as $credentialSetting) {
                $credentialSetting = get_object_vars($credentialSetting);

                try {
                    $encrypter->decryptString($credentialSetting['setting_value']);
                } catch (\Exception $e) {
                    $this->encryptCredential($credentialSetting);
                }
            }
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    private function getCredentialSettings()
    {
        return DB::table('plugin_settings')
            ->where('plugin_name', self::PLUGIN_NAME_SETTINGS)
            ->whereIn('setting_name', self::PLUGIN_CREDENTIALS_SETTINGS)
            ->get();
    }

    private function encryptCredential($credentialSetting)
    {
        $encrypter = new APIKeyEncryption();

        $settingValue = $this->extractSettingValue($credentialSetting['setting_value']);
        $encryptedValue = $encrypter->encryptString($settingValue);

        DB::table('plugin_settings')
            ->where('context_id', $credentialSetting['context_id'])
            ->where('plugin_name', self::PLUGIN_NAME_SETTINGS)
            ->where('setting_name', $credentialSetting['setting_name'])
            ->update(['setting_value' => $encryptedValue]);
    }

    private function extractSettingValue($settingValue)
    {
        $jwtParts = explode('.', $settingValue);
        if (count($jwtParts) == 3) {
            $header = json_decode(base64_decode($jwtParts[0]), true);
            if (!isset($header['alg']) || !isset($header['typ'])) {
                return $settingValue;
            }

            $payload = base64_decode($jwtParts[1]);
            return trim($payload, '"');
        }

        return $settingValue;
    }
}
