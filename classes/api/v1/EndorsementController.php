<?php

namespace APP\plugins\generic\plauditPreEndorsement\classes\api\v1;

use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;

class EndorsementController extends PKPBaseController
{
    private const EDITORIAL_ROLES = [
        Role::ROLE_ID_SITE_ADMIN,
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
    ];

    public function getHandlerPath(): string
    {
        return 'endorsements';
    }

    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer(array_merge(self::EDITORIAL_ROLES, [Role::ROLE_ID_AUTHOR])),
        ];
    }

    public function getGroupRoutes(): void
    {
        Route::get('{submissionId}', $this->getEndorsements(...))
            ->name('endorsements.getMany');

        Route::post('{submissionId}', $this->addEndorsement(...))
            ->name('endorsements.add');

        Route::put('{submissionId}/{endorsementId}', $this->editEndorsement(...))
            ->name('endorsements.edit');

        Route::delete('{submissionId}/{endorsementId}', $this->deleteEndorsement(...))
            ->name('endorsements.delete');

        Route::post('{submissionId}/{endorsementId}/send', $this->sendEndorsement(...))
            ->name('endorsements.send');
    }

    public function getEndorsements(IlluminateRequest $request): JsonResponse
    {
        $submission = $this->getAuthorizedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $publicationIds = $this->collectPublicationIds($submission);

        $endorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$this->getContextId()])
            ->filterByPublicationIds($publicationIds)
            ->getMany()
            ->toArray();

        $items = array_map(
            fn (Endorsement $endorsement) => $this->endorsementToArray($endorsement),
            $endorsements
        );

        return response()->json([
            'items' => array_values($items),
            'itemsMax' => count($items),
        ], Response::HTTP_OK);
    }

    public function addEndorsement(IlluminateRequest $request): JsonResponse
    {
        $submission = $this->getAuthorizedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $publication = $submission->getCurrentPublication();

        $name = $request->input('name');
        $email = $request->input('email');

        $errors = $this->validateEndorsementData($name, $email, $publication, $this->getContextId());
        if (!empty($errors)) {
            return response()->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $endorsement = Repo::endorsement()->newDataObject([
            'contextId' => $this->getContextId(),
            'name' => $name,
            'email' => $email,
            'publicationId' => $publication->getId(),
        ]);
        Repo::endorsement()->add($endorsement);

        $plugin = $this->getPlugin();
        if (!$submission->getData('submissionProgress')) {
            $plugin->sendEmailToEndorser($publication, $endorsement);
        }

        $plugin->writeOnActivityLog(
            $submission->getId(),
            'plugins.generic.plauditPreEndorsement.log.endorsementAdded',
            ['endorserName' => $endorsement->getName(), 'endorserEmail' => $endorsement->getEmail()]
        );

        return response()->json($this->endorsementToArray($endorsement), Response::HTTP_CREATED);
    }

    public function editEndorsement(IlluminateRequest $request): JsonResponse
    {
        $submission = $this->getAuthorizedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $endorsement = $this->getEndorsementForSubmission($request, $submission);
        if ($endorsement instanceof JsonResponse) {
            return $endorsement;
        }

        $name = $request->input('name');
        $email = $request->input('email');

        $publication = $submission->getCurrentPublication();
        $errors = $this->validateEndorsementData(
            $name,
            $email,
            $publication,
            $this->getContextId(),
            $endorsement->getId()
        );
        if (!empty($errors)) {
            return response()->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $endorserChanged = ($email != $endorsement->getEmail());
        Repo::endorsement()->edit($endorsement, ['name' => $name, 'email' => $email]);

        $updatedEndorsement = Repo::endorsement()->get($endorsement->getId(), $this->getContextId());

        if (!$submission->getData('submissionProgress')) {
            $this->getPlugin()->sendEmailToEndorser($publication, $updatedEndorsement, $endorserChanged);
        }

        return response()->json($this->endorsementToArray($updatedEndorsement), Response::HTTP_OK);
    }

    public function deleteEndorsement(IlluminateRequest $request): JsonResponse
    {
        $submission = $this->getAuthorizedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $endorsement = $this->getEndorsementForSubmission($request, $submission);
        if ($endorsement instanceof JsonResponse) {
            return $endorsement;
        }

        Repo::endorsement()->delete($endorsement);

        $this->getPlugin()->writeOnActivityLog(
            $submission->getId(),
            'plugins.generic.plauditPreEndorsement.log.endorsementRemoved',
            ['endorserName' => $endorsement->getName(), 'endorserEmail' => $endorsement->getEmail()]
        );

        return response()->json([
            'message' => __('plugins.generic.plauditPreEndorsement.api.endorsementDeleted'),
        ], Response::HTTP_OK);
    }

    public function sendEndorsement(IlluminateRequest $request): JsonResponse
    {
        $submission = $this->getAuthorizedSubmission($request, editorialOnly: true);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $endorsement = $this->getEndorsementForSubmission($request, $submission);
        if ($endorsement instanceof JsonResponse) {
            return $endorsement;
        }

        $plugin = $this->getPlugin();
        (new EndorsementService($this->getContextId(), $plugin))->sendEndorsement($endorsement);

        return response()->json([
            'message' => __('plugins.generic.plauditPreEndorsement.sendEndorsementToPlauditNotification'),
        ], Response::HTTP_OK);
    }

    /**
     * Loads the submission from the route and enforces access control:
     * - must exist and belong to the current context
     * - editorial roles pass through
     * - authors must have a stage assignment on this submission
     * - if $editorialOnly is true, authors are rejected outright
     */
    private function getAuthorizedSubmission(IlluminateRequest $request, bool $editorialOnly = false): Submission|JsonResponse
    {
        $submissionId = (int) $request->route('submissionId');
        $submission = \APP\facades\Repo::submission()->get($submissionId);

        if (!$submission || (int) $submission->getData('contextId') !== $this->getContextId()) {
            return response()->json(
                ['error' => __('plugins.generic.plauditPreEndorsement.api.submissionNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        $user = Application::get()->getRequest()->getUser();
        if (!$user) {
            return response()->json(
                ['error' => __('api.403.unauthorized')],
                Response::HTTP_FORBIDDEN
            );
        }

        $userRoles = (array) ($this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES) ?? []);
        $hasEditorialRole = !empty(array_intersect($userRoles, self::EDITORIAL_ROLES));

        if ($hasEditorialRole) {
            return $submission;
        }

        if ($editorialOnly) {
            return response()->json(
                ['error' => __('api.403.unauthorized')],
                Response::HTTP_FORBIDDEN
            );
        }

        $isAssignedAuthor = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withUserId($user->getId())
            ->get()
            ->isNotEmpty();

        if (!$isAssignedAuthor) {
            return response()->json(
                ['error' => __('api.403.unauthorized')],
                Response::HTTP_FORBIDDEN
            );
        }

        return $submission;
    }

    /**
     * Loads the endorsement from the route and verifies it belongs to one of
     * the submission's publications — prevents cross-submission access.
     */
    private function getEndorsementForSubmission(IlluminateRequest $request, Submission $submission): Endorsement|JsonResponse
    {
        $endorsementId = (int) $request->route('endorsementId');
        $endorsement = Repo::endorsement()->get($endorsementId, $this->getContextId());

        if (!$endorsement) {
            return response()->json(
                ['error' => __('plugins.generic.plauditPreEndorsement.api.endorsementNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        if (!in_array($endorsement->getPublicationId(), $this->collectPublicationIds($submission), true)) {
            return response()->json(
                ['error' => __('plugins.generic.plauditPreEndorsement.api.endorsementNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        return $endorsement;
    }

    private function collectPublicationIds(Submission $submission): array
    {
        $publications = $submission->getData('publications') ?: [];
        $ids = [];
        foreach ($publications as $publication) {
            $ids[] = $publication->getId();
        }
        return $ids;
    }

    private function getContextId(): int
    {
        return (int) Application::get()->getRequest()->getContext()->getId();
    }

    private function getPlugin()
    {
        return PluginRegistry::getPlugin('generic', 'plauditpreendorsementplugin');
    }

    private function endorsementToArray(Endorsement $endorsement): array
    {
        return [
            'id' => $endorsement->getId(),
            'contextId' => $endorsement->getContextId(),
            'publicationId' => $endorsement->getPublicationId(),
            'name' => $endorsement->getName(),
            'email' => $endorsement->getEmail(),
            'status' => $endorsement->getStatus(),
            'orcid' => $endorsement->getOrcid(),
            'emailCount' => $endorsement->getEmailCount(),
        ];
    }

    private function validateEndorsementData(?string $name, ?string $email, $publication, int $contextId, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($name)) {
            $errors['name'] = [__('plugins.generic.plauditPreEndorsement.endorserNameRequired')];
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = [__('plugins.generic.plauditPreEndorsement.endorsementEmailInvalid')];
            return $errors;
        }

        $existingEndorsement = Repo::endorsement()->getByEmail($email, $publication->getId(), $contextId);
        if ($existingEndorsement && $existingEndorsement->getId() !== $excludeId) {
            $errors['email'] = [__('plugins.generic.plauditPreEndorsement.endorserEmailDuplicate')];
            return $errors;
        }

        $authors = \APP\facades\Repo::author()->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();

        foreach ($authors as $author) {
            if ($author->getData('email') === $email) {
                $errors['email'] = [__('plugins.generic.plauditPreEndorsement.endorsementFromAuthor')];
                break;
            }
        }

        return $errors;
    }
}
