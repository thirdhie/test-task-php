<?php
declare(strict_types=1);

namespace App\Http\Controllers\MailChimp;

use App\Database\Entities\MailChimp\MailChimpMember;
use App\Http\Controllers\Controller;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mailchimp\Mailchimp;

class MembersController extends Controller
{
    /**
     * @var \Mailchimp\Mailchimp
     */
    private $mailChimp;

    /**
     * ListsController constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     * @param \Mailchimp\Mailchimp $mailchimp
     */
    public function __construct(EntityManagerInterface $entityManager, Mailchimp $mailchimp)
    {
        parent::__construct($entityManager);

        $this->mailChimp = $mailchimp;
    }

    /**
     * Add a member to mailchimp list.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request): JsonResponse
    {
        // Instantiate entity
        $member = new MailChimpMember($request->all());
        // Validate entity
        $validator = $this->getValidationFactory()->make($member->toMailChimpArray(), $member->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Save member into db
            $this->saveEntity($member);
            // Save member into MailChimp
            $response = $this->mailChimp->post('/lists/' . $request->listId . '/members', $list->toMailChimpArray());
            // Set MailChimp id on the member and save member into db
            $this->saveEntity($members->setMailChimpId($response->get('id')));
        } catch (Exception $exception) {
            // Return error response if something goes wrong
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($list->toArray());
    }

    /**
     * Retrieve and return MailChimp list member.
     *
     * @param string $subscriberHash
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $subscriberHash): JsonResponse
    {
        $member = $this->entityManager->getRepository(MailChimpMember::class)->find($subscriberHash);

        if ($member === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpMember[%s] not found', $subscriberHash)],
                404
            );
        }

        return $this->successfulResponse($member->toArray());
    }

    /**
     * Update Member.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $subscriberHash
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $subscriberHash): JsonResponse
    {
        $member = $this->entityManager->getRepository(MailChimpMember::class)->find($subscriberHash);

        if ($member === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpMember[%s] not found', $subscriberHash)],
                404
            );
        }

        // Update member properties
        $member->fill($request->all());

        // Validate entity
        $validator = $this->getValidationFactory()->make($member->toMailChimpArray(), $member->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Update member into database
            $this->saveEntity($member);
            // Update member into MailChimp
            $this->mailChimp->patch(\sprintf('/lists/' . $request->listId . '/members/' . $subscriberHash, $member->getMailChimpId()), $member->toMailChimpArray());
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($member->toArray());
    }


    /**
     * Remove Member.
     *
     * @param string $subscriberHash
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove(string $subscriberHash): JsonResponse
    {
        $member = $this->entityManager->getRepository(MailChimpMember::class)->find($subscriberHash);

        if ($member === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpMember[%s] not found', $subscriberHash)],
                404
            );
        }

        try {
            // Remove member from database
            $this->removeEntity($member);
            // Remove member from MailChimp
            $this->mailChimp->delete(\sprintf('/lists/' . $request->listId . '/members/' . $subscriberHash, $member->getMailChimpId()));
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse([]);
    }
}
