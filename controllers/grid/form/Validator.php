<?php

namespace APP\plugins\generic\plauditPreEndorsement\controllers\grid\form;

use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use APP\plugins\generic\plauditPreEndorsement\controllers\grid\form\EndorsementForm;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;

class Validator
{
    public static function addValidations(EndorsementForm $form, $contextId, $submissionId)
    {
        $form->addCheck(new FormValidatorPost($form));
        $form->addCheck(new FormValidatorCSRF($form));
        $form->addCheck(new \PKP\form\validation\FormValidator($form, 'endorserName', 'required', 'validator.required'));
        $form->addCheck(new \PKP\form\validation\FormValidatorEmail($form, 'endorserEmail', 'required', 'user.profile.form.emailRequired'));
        $form->addCheck(new \PKP\form\validation\FormValidatorCustom($form, 'endorserEmail', 'required', 'user.register.form.emailExists', function ($endorserEmail) use ($contextId, $submissionId) {
            $submission = Repo::submission()->get($submissionId);
            $publication = $submission->getCurrentPublication();
            return is_null(Repo::endorsement()->getByEmail($endorserEmail, $publication->getId(), $contextId));
        }));
        $form->addCheck(new \PKP\form\validation\FormValidatorCustom($form, 'endorserEmail', 'required', 'plugins.generic.plauditPreEndorsement.addEndorsementForm.emailExistsInSubmissionAuthors', function ($endorserEmail) use ($submissionId) {
            $submission = Repo::submission()->get($submissionId);
            $publication = $submission->getCurrentPublication();
            $authors = $publication->getData('authors');

            foreach ($authors as $author) {
                if ($author->getData('email') == $endorserEmail) {
                    return false;
                }
            }
            return true;
        }));
    }
}
