{*
  * Copyright (c) 2022 Lepidus Tecnologia
  * Copyright (c) 2022 SciELO
  * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
  *
  *}

<link rel="stylesheet" type="text/css" href="/plugins/generic/plauditPreEndorsement/styles/endorserStep3StyleSheet.css">

{fbvFormSection id="endorserSection" label="plugins.generic.plauditPreEndorsement.endorsement" description="plugins.generic.plauditPreEndorsement.endorsement.description"}
    {fbvElement type="text" label="plugins.generic.plauditPreEndorsement.endorserName" name="endorserName" id="endorserName" value=$endorserName size=$fbvStyles.size.MEDIUM}
    {fbvElement type="email" label="plugins.generic.plauditPreEndorsement.endorserEmail" name="endorserEmail" id="endorserEmail" value=$endorserEmail maxlength="90" size=$fbvStyles.size.MEDIUM}
{/fbvFormSection}