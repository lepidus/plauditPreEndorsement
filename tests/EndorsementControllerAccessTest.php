<?php

use APP\plugins\generic\plauditPreEndorsement\classes\api\v1\EndorsementController;
use PHPUnit\Framework\TestCase;
use PKP\security\Role;

final class EndorsementControllerAccessTest extends TestCase
{
    public function testManagerAndSiteAdminAccessAnySubmissionOfTheContext(): void
    {
        foreach ([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN] as $roleId) {
            $this->assertTrue(EndorsementController::canAccessSubmission([$roleId], [], false));
            $this->assertTrue(EndorsementController::canAccessSubmission([$roleId], [], false, true));
        }
    }

    public function testUnassignedSubEditorAndAssistantAreDenied(): void
    {
        foreach ([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT] as $roleId) {
            $this->assertFalse(EndorsementController::canAccessSubmission([$roleId], [], false));
            $this->assertFalse(EndorsementController::canAccessSubmission([$roleId], [], false, true));
        }
    }

    public function testAssignedSubEditorAndAssistantAreAllowed(): void
    {
        foreach ([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT] as $roleId) {
            $this->assertTrue(EndorsementController::canAccessSubmission([$roleId], [$roleId], false));
            $this->assertTrue(EndorsementController::canAccessSubmission([$roleId], [$roleId], false, true));
        }
    }

    public function testAssignmentInRoleNoLongerHeldIsIgnored(): void
    {
        $this->assertFalse(EndorsementController::canAccessSubmission([Role::ROLE_ID_ASSISTANT], [Role::ROLE_ID_SUB_EDITOR], false));
    }

    public function testAssignedAuthorIsAllowedOnlyForNonEditorialOperations(): void
    {
        $this->assertTrue(EndorsementController::canAccessSubmission([Role::ROLE_ID_AUTHOR], [Role::ROLE_ID_AUTHOR], true));
        $this->assertFalse(EndorsementController::canAccessSubmission([Role::ROLE_ID_AUTHOR], [Role::ROLE_ID_AUTHOR], true, true));
    }

    public function testUnassignedAuthorIsDenied(): void
    {
        $this->assertFalse(EndorsementController::canAccessSubmission([Role::ROLE_ID_AUTHOR], [], false));
    }

    public function testSubEditorAssignedOnlyAsAuthorCannotSendEndorsement(): void
    {
        $userRoles = [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR];

        $this->assertTrue(EndorsementController::canAccessSubmission($userRoles, [Role::ROLE_ID_AUTHOR], true));
        $this->assertFalse(EndorsementController::canAccessSubmission($userRoles, [Role::ROLE_ID_AUTHOR], true, true));
    }
}
