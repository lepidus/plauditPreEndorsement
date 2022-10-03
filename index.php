<?php

/**
 * @defgroup plugins_generic_plauditPreEndorsement Plaudit Pre-Endorsement Plugin
 */

/**
 * @file plugins/generic/plauditPreEndorsement/index.php
 *
 * Copyright (c) 2022 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @ingroup plugins_generic_plauditPreEndorsement
 * @brief Wrapper for Plaudit Pre-Endorsement plugin.
 *
 */

require_once('PlauditPreEndorsementPlugin.inc.php');

return new PlauditPreEndorsementPlugin();
