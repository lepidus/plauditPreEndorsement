<?php

use PHPUnit\Framework\TestCase;

final class EndorserFieldWorkflowTemplateTest extends TestCase
{
    private const MALICIOUS_NAME = '<img src=x onerror=alert(1)> {{ constructor.constructor("alert(2)")() }}';
    private const MALICIOUS_EMAIL = '"{{7*7}}"@example.com';

    private $compileDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plauditWorkflowTemplateTest_' . uniqid();
        mkdir($this->compileDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->compileDir . DIRECTORY_SEPARATOR . '*') as $compiledFile) {
            unlink($compiledFile);
        }
        rmdir($this->compileDir);
        parent::tearDown();
    }

    private function renderWorkflowTemplate(array $variables): string
    {
        $smarty = new Smarty();
        $smarty->setCompileDir($this->compileDir);
        $smarty->registerPlugin('function', 'translate', function ($params) {
            return $params['key'];
        });
        $smarty->registerPlugin('function', 'fbvElement', function ($params) {
            return '<input name="' . $params['name'] . '" value="' . $params['value'] . '">';
        });
        $smarty->registerPlugin('function', 'csrf', function ($params) {
            return '"csrf"';
        });
        $smarty->assign(array_merge([
            'canEditEndorsement' => false,
            'canRemoveEndorsement' => false,
            'canSendEndorsementManually' => false,
            'endorsementStatus' => 1,
            'endorsementStatusSuffix' => 'Confirmed',
            'endorserName' => self::MALICIOUS_NAME,
            'endorserEmail' => self::MALICIOUS_EMAIL,
            'endorserOrcid' => 'https://orcid.org/0000-0002-1825-0097',
            'fbvStyles' => ['size' => ['MEDIUM' => 'medium']],
            'submissionId' => 10,
            'updateEndorserUrl' => 'https://example.com/pre-endorsement-handler/updateEndorser',
            'removeEndorsementUrl' => 'https://example.com/pre-endorsement-handler/removeEndorsement',
            'sendEndorsementManuallyUrl' => 'https://example.com/pre-endorsement-handler/sendEndorsementManually',
        ], $variables));

        return $smarty->fetch('file:' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'endorserFieldWorkflow.tpl');
    }

    /**
     * Text nodes inside the element marked with v-pre are not compiled by the
     * Vue instance that mounts the OPS 3.3 workflow page.
     */
    private function getTextInsideVPre(string $output): array
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>' . $output . '</body>');
        libxml_clear_errors();

        $texts = [];
        foreach ((new DOMXPath($document))->query('//div[@id="updateEndorserForm"][@v-pre]//span') as $span) {
            $texts[] = trim($span->textContent);
        }

        return $texts;
    }

    public function testEndorserDataIsRenderedAsTextInsideVPre(): void
    {
        $output = $this->renderWorkflowTemplate([]);

        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringContainsString('<div class="pkp_form" id="updateEndorserForm" v-pre>', $output);
        $texts = $this->getTextInsideVPre($output);
        $this->assertContains(self::MALICIOUS_NAME, $texts);
        $this->assertContains(self::MALICIOUS_EMAIL, $texts);
    }

    public function testEditableFieldsAreAlsoInsideVPre(): void
    {
        $output = $this->renderWorkflowTemplate(['canEditEndorsement' => true, 'endorsementStatus' => 0]);

        $this->assertStringNotContainsString('<img', $output);
        $this->assertMatchesRegularExpression(
            '/<div class="pkp_form" id="updateEndorserForm" v-pre>.*name="endorserNameWorkflow".*<\/div>/s',
            $output
        );
    }
}
