<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrupdateShareableLinkRequest;
use App\Models\FileEntry;
use App\Models\ShareableLink;
use App\Models\User;
use App\Services\Links\CrupdateShareableLink;
use App\Services\Links\GetShareableLink;
use App\Services\Links\ValidatesLinkPassword;
use Common\Core\BaseController;
use Common\Files\Response\FileResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DirectLinkController extends BaseController
{
    use ValidatesLinkPassword;

    public function __construct(
        private Request $request,
        private ShareableLink $link,
    ) {}

    public function show(string $linkHash, string $fileHash, string $extension)
    {
        $link = ShareableLink::where('hash', $linkHash)
            ->with('entry')
            ->firstOrFail();

        $entry = $link->entry;

        if (
            !$link->allow_direct ||
            !$entry ||
            $entry->trashed() ||
            !$this->passwordIsValid($link)
        ) {
            abort(404);
        }

        if ($entry->type === 'folder') {
            $entry = $entry
                ->allChildren()
                ->where('hash', $fileHash)
                ->firstOrFail();
        }

        return (new FileResponseFactory())->create($entry);
    }
}
