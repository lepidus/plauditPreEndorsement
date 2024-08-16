<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use APP\plugins\generic\plauditPreEndorsement\classes\api\APIKeyEncryption;
use Firebase\JWT\JWT;

class EncryptLegacyCredentials extends Migration
{
    public function up(): void
    {
        $credentialSettings = $this->getCredentialSettings();

        if (!empty($credentialSettings)) {
            $credentials = $this->getCredentials($credentialSettings);

            foreach ($credentials as $contextId => $setting) {
                $orcidClientId = $setting['orcidClientId'];
                $orcidClientSecret = $setting['orcidClientSecret'];
                $plauditAPISecret = $setting['plauditAPISecret'];

                try {
                    APIKeyEncryption::decryptString($orcidClientId);
                } catch (\Exception $e) {
                    if ($e instanceof \UnexpectedValueException) {
                        $this->encryptCredentials($contextId, $orcidClientId, $orcidClientSecret, $plauditAPISecret);
                    }
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
            ->whereIn('setting_name', [
                'orcidClientId',
                'orcidClientSecret',
                'plauditAPISecret'
            ])
            ->get();
    }

    private function getCredentials($credentialSettings)
    {
        $credentials = [];
        foreach ($credentialSettings as $credentialSetting) {
            $contextId = $credentialSetting->context_id;
            $credentials[$contextId][$credentialSetting->setting_name] = $credentialSetting->setting_value;
        }
        return $credentials;
    }

    private function encryptCredentials($contextId, $orcidClientId, $orcidClientSecret, $plauditAPISecret)
    {
        $credentials = [
            'orcidClientId' => $orcidClientId,
            'orcidClientSecret' => $orcidClientSecret,
            'plauditAPISecret' => $plauditAPISecret
        ];

        foreach ($credentials as $settingName => $settingValue) {
            $encryptedValue = APIKeyEncryption::encryptString($settingValue);

            DB::table('plugin_settings')
                ->where('context_id', $contextId)
                ->where('setting_name', $settingName)
                ->update(['setting_value' => $encryptedValue]);
        }
    }
}
