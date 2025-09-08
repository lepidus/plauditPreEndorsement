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
            $credentialsForContexts = $this->mapCredentialsForContexts($credentialSettings);

            foreach ($credentialsForContexts as $contextId => $credentials) {
                $orcidClientId = $credentials['orcidClientId'];
                $orcidClientSecret = $credentials['orcidClientSecret'];
                $plauditAPISecret = $credentials['plauditAPISecret'];

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
            ->where('plugin_name', self::PLUGIN_NAME_SETTINGS)
            ->whereIn('setting_name', self::PLUGIN_CREDENTIALS_SETTINGS)
            ->get();
    }

    private function mapCredentialsForContexts($credentialSettings)
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
                ->where('plugin_name', self::PLUGIN_NAME_SETTINGS)
                ->where('setting_name', $settingName)
                ->update(['setting_value' => $encryptedValue]);
        }
    }
}
