# Plaudit Pre-Endorsement

## Description

This plugin allows authors to pre-endorse their manuscripts before the posting takes place. Endorsers receive an ORCID verification email, and confirmed endorsements are sent to the Plaudit API upon publication.

## Compatibility

This plugin is compatible with the following PKP applications:

- OPS 3.5.0-x

## Installation

1. Enter the administration area of your OPS website through the __Dashboard__.
2. Navigate to `Website Settings` > `Plugins` > `Installed Plugins` > `Upload a new plugin`.
3. Under __Upload file__ select the file __plauditPreEndorsement.tar.gz__, downloaded from the latest release.
4. Click __Save__ and the plugin will be installed on your website.

## Setting

After installing this plugin, make sure to configure it. In the `Installed plugins` page, find the Plaudit Pre-Endorsement plugin and open its settings. You should inform the following settings:

- **ORCID API Type**: Public, Public Sandbox, Member, or Member Sandbox
- **ORCID Client ID**: Your ORCID application client ID
- **ORCID Client Secret**: Your ORCID application client secret
- **Plaudit API Secret**: Your Plaudit API secret key

After completing this configuration, the plugin is ready for use.

## Features

- **Submission Wizard**: Authors can add endorsers (name and email) during manuscript submission
- **Workflow Tab**: "Pre-Endorsement" menu item in the editorial workflow shows all endorsements with status
- **ORCID Verification**: Endorsers receive email with ORCID OAuth link for identity verification
- **Plaudit Integration**: Confirmed endorsements are automatically sent to Plaudit API when the submission is published
- **Scheduled Tasks**: Automatic re-sending of ORCID requests and endorsement submission to Plaudit

## Limitations

- Endorsements can only be sent to Plaudit after the submission has a DOI indexed by CrossRef
- The endorser's ORCID must have publicly listed works
- An author of the manuscript cannot endorse their own submission

## License

__This plugin is licensed under the GNU General Public License v3.0__

__Copyright (c) 2022 - 2024 Lepidus Tecnologia__

__Copyright (c) 2022 - 2024 SciELO__
