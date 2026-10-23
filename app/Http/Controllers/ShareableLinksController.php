<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrupdateShareableLinkRequest;
use App\Models\FileEntry;
use App\Models\ShareableLink;
use App\Services\Entries\DuplicateEntries;
use App\Services\Links\CrupdateShareableLink;
use App\Services\Links\GetShareableLink;
use App\Services\Links\ValidatesLinkPassword;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShareableLinksController extends BaseController
{
    use ValidatesLinkPassword;

    public function __construct(
        private Request $request,
        private ShareableLink $link,
    ) {}

    public function show(int|string $idOrHash)
    {
        $data = (new GetShareableLink())->execute(
            $idOrHash,
            request('loader', 'shareableLinkPage'),
            $this->request->all(),
        );

        $link = $data['link'];
        if (!$link || !$link->entry || $link->entry->trashed()) {
            if (isApiRequest()) {
                return $this->success(['link' => null]);
            } else {
                abort(404);
            }
        }

        $data['passwordInvalid'] = !$this->passwordIsValid($link);

        $this->authorize('show', $link);

        // don't return data if password is invalid
        if ($data['passwordInvalid']) {
            $data = ['passwordInvalid' => true];
        }

        return $this->renderClientOrApi([
            'data' => $data,
            'pageName' => $data['passwordInvalid']
                ? null
                : 'shareable-link-page',
        ]);
    }

    public function store(
        int $entryId,
        CrupdateShareableLinkRequest $request,
        CrupdateShareableLink $action,
    ) {
        $this->authorize('create', ShareableLink::class);
        $this->authorize('update', [FileEntry::class, [$entryId]]);

        $params = $request->all();
        $params['userId'] = $request->user()->id;
        $params['entryId'] = $entryId;

        if (request('enableDirectLinks') && !isset($params['allowDirect'])) {
            $params['allowDirect'] = true;
        }

        $existingLink = $this->link->where('entry_id', $entryId)->first();
        $link = $existingLink ?: $action->execute($params);

        return $this->success(['link' => $link]);
    }

    public function update(
        int $entryId,
        CrupdateShareableLinkRequest $request,
        CrupdateShareableLink $action,
    ): JsonResponse {
        $link = $this->link->where('entry_id', $entryId)->firstOrFail();

        $this->authorize('update', $link);

        $params = $request->all();
        $params['userId'] = $request->user()->id;

        $action->execute($params, $link);

        return $this->success(['link' => $link]);
    }

    public function destroy(int $entryId): JsonResponse
    {
        $link = $this->link->where('entry_id', $entryId)->firstOrFail();

        $this->authorize('destroy', $link);

        $link->delete();

        return $this->success();
    }

    public function importIntoOwnDrive(int $linkId)
    {
        $link = $this->link->where('id', $linkId)->firstOrFail();

        $this->authorize('importIntoOwnDrive', $link);

        (new DuplicateEntries(
            entryIds: [$link->entry_id],
            destinationId: null,
            ownerId: Auth::id(),
        ))->execute();

        return $this->success();
    }
}
