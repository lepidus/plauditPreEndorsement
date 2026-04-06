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
use APP\plugins\generic\plauditPreEndorsement\classes\facades\Repo;
use APP\plugins\generic\plauditPreEndorsement\classes\endorsement\Endorsement;
use APP\plugins\generic\plauditPreEndorsement\classes\EndorsementService;

class EndorsementController extends PKPBaseController
{
    public function getHandlerPath(): string
    {
        return 'endorsements';
    }

    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
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
        $submission = $this->getValidatedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $contextId = $this->getContextId();
        $publications = $submission->getData('publications');
        $publicationIds = [];

        if (!empty($publications)) {
            foreach ($publications as $publication) {
                $publicationIds[] = $publication->getId();
            }
        }

        $endorsements = Repo::endorsement()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByPublicationIds($publicationIds)
            ->getMany()
            ->toArray();

        $items = array_map(function (Endorsement $endorsement) {
            return $this->endorsementToArray($endorsement);
        }, $endorsements);

        return response()->json([
            'items' => array_values($items),
            'itemsMax' => count($items),
        ], Response::HTTP_OK);
    }

    public function addEndorsement(IlluminateRequest $request): JsonResponse
    {
        $submission = $this->getValidatedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $contextId = $this->getContextId();
        $publication = $submission->getCurrentPublication();

        $name = $request->input('name');
        $email = $request->input('email');

        $errors = $this->validateEndorsementData($name, $email, $contextId, $submission->getId(), $publication->getId());
        if (!empty($errors)) {
            return response()->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $endorsement = Repo::endorsement()->newDataObject([
            'contextId' => $contextId,
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
        $submission = $this->getValidatedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $contextId = $this->getContextId();
        $endorsementId = (int) $request->route('endorsementId');
        $endorsement = Repo::endorsement()->get($endorsementId, $contextId);

        if (!$endorsement) {
            return response()->json(
                ['error' => 'Endorsement not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $name = $request->input('name');
        $email = $request->input('email');

        $errors = $this->validateEndorsementData($name, $email, $contextId, $submission->getId(), $endorsement->getPublicationId(), $endorsementId);
        if (!empty($errors)) {
            return response()->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $endorserChanged = ($email != $endorsement->getEmail());
        $params = ['name' => $name, 'email' => $email];
        Repo::endorsement()->edit($endorsement, $params);

        $publication = $submission->getCurrentPublication();
        $updatedEndorsement = Repo::endorsement()->get($endorsementId, $contextId);

        if (!$submission->getData('submissionProgress')) {
            $plugin = $this->getPlugin();
            $plugin->sendEmailToEndorser($publication, $updatedEndorsement, $endorserChanged);
        }

        return response()->json($this->endorsementToArray($updatedEndorsement), Response::HTTP_OK);
    }

    public function deleteEndorsement(IlluminateRequest $request): JsonResponse
    {
        $submission = $this->getValidatedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $contextId = $this->getContextId();
        $endorsementId = (int) $request->route('endorsementId');
        $endorsement = Repo::endorsement()->get($endorsementId, $contextId);

        if (!$endorsement) {
            return response()->json(
                ['error' => 'Endorsement not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        Repo::endorsement()->delete($endorsement);

        $this->getPlugin()->writeOnActivityLog(
            $submission->getId(),
            'plugins.generic.plauditPreEndorsement.log.endorsementRemoved',
            ['endorserName' => $endorsement->getName(), 'endorserEmail' => $endorsement->getEmail()]
        );

        return response()->json(['message' => 'Endorsement deleted'], Response::HTTP_OK);
    }

    public function sendEndorsement(IlluminateRequest $request): JsonResponse
    {
        $submission = $this->getValidatedSubmission($request);
        if ($submission instanceof JsonResponse) {
            return $submission;
        }

        $contextId = $this->getContextId();
        $endorsementId = (int) $request->route('endorsementId');
        $endorsement = Repo::endorsement()->get($endorsementId, $contextId);

        if (!$endorsement) {
            return response()->json(
                ['error' => 'Endorsement not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $plugin = $this->getPlugin();
        $endorsementService = new EndorsementService($contextId, $plugin);
        $endorsementService->sendEndorsement($endorsement);

        return response()->json([
            'message' => __('plugins.generic.plauditPreEndorsement.sendEndorsementToPlauditNotification'),
        ], Response::HTTP_OK);
    }

    private function getValidatedSubmission(IlluminateRequest $request): Submission|JsonResponse
    {
        $submissionId = (int) $request->route('submissionId');
        $submission = \APP\facades\Repo::submission()->get($submissionId);

        if (!$submission) {
            return response()->json(
                ['error' => 'Submission not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $contextId = $this->getContextId();
        if ((int) $submission->getData('contextId') !== $contextId) {
            return response()->json(
                ['error' => 'Submission not found in this context'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $submission;
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

    private function validateEndorsementData(?string $name, ?string $email, int $contextId, int $submissionId, int $publicationId, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($name)) {
            $errors['name'] = [__('plugins.generic.plauditPreEndorsement.endorserNameRequired')];
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = [__('plugins.generic.plauditPreEndorsement.endorserEmailInvalid')];
        }

        if (!empty($email)) {
            $existingEndorsement = Repo::endorsement()->getByEmail($email, $publicationId, $contextId);
            if ($existingEndorsement && $existingEndorsement->getId() !== $excludeId) {
                $errors['email'] = [__('plugins.generic.plauditPreEndorsement.endorserEmailDuplicate')];
            }

            $submission = \APP\facades\Repo::submission()->get($submissionId);
            if ($submission) {
                $publication = $submission->getCurrentPublication();
                $authors = $publication->getData('authors');
                foreach ($authors as $author) {
                    if ($author->getData('email') === $email) {
                        $errors['email'] = [__('plugins.generic.plauditPreEndorsement.endorserEmailIsAuthor')];
                        break;
                    }
                }
            }
        }

        return $errors;
    }
}
