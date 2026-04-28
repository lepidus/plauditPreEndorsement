<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use APP\plugins\generic\plauditPreEndorsement\PlauditPreEndorsementPlugin;
use APP\facades\Repo;

class UpdateEmailTemplatesMigration extends Migration
{
    public function up(): void
    {
        $plugin = new PlauditPreEndorsementPlugin();
        $plugin->pluginPath = 'plugins/generic/plauditPreEndorsement';
        $emailLocales = $this->getEmailLocales($plugin);

        $plugin->addLocaleData();
        Repo::emailTemplate()->dao->installEmailTemplates(
            $plugin->getInstallEmailTemplatesFile(),
            $emailLocales
        );
    }

    private function getEmailLocales($plugin): array
    {
        $pluginLocalesDirectory = $plugin->getPluginPath() . '/locale/';
        $emailLocales = [];
        $localeDirectories = scandir($pluginLocalesDirectory);

        foreach ($localeDirectories as $directory) {
            if ($directory !== '.' && $directory !== '..' && is_dir($pluginLocalesDirectory . $directory)) {
                $emailsPoFile = $pluginLocalesDirectory . $directory . '/emails.po';
                if (file_exists($emailsPoFile)) {
                    $emailLocales[] = $directory;
                }
            }
        }

        return $emailLocales;
    }
}
