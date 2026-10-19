<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientMaterialRequest;
use App\Models\Attachment;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ClientMaterialController extends Controller
{
    public function index(Request $request, Client $client)
    {
        $this->authorize('viewMaterials', $client);
        $filters = $request->validate(['search' => 'nullable|string|max:255', 'category' => ['nullable', Rule::in(array_keys(Attachment::CLIENT_MATERIAL_CATEGORIES))]]);
        $materials = $client->attachments()->whereNotNull('client_material_category')->with('uploader:id,name')
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('client_material_category', $category))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($match) => $match->where('original_name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
            ->latest()->orderByDesc('id')->paginate(\App\Support\TableListing::perPage(20))->withQueryString();

        return view('clients.materials', compact('client', 'materials', 'filters'));
    }

    public function store(StoreClientMaterialRequest $request, Client $client)
    {
        $data = $request->validated();
        app(\App\Services\PrivateAttachmentBatch::class)->upload($client, $request->file('files') ?? [$request->file('file')], [
            'type' => 'document', 'client_material_category' => $data['category'], 'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('clients.materials.index', $client)->with('success', 'Materiale aggiunto all’archivio del cliente.');
    }
}
