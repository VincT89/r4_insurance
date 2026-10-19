<?php
namespace App\Domain\Core\Queries;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

class ClientQuery
{
    public function forIndex(array $filters = []): Builder
    {
        $query = $this->forDropdown()->with('commercialUser:id,name')->withCount('policies');
        if (($filters['client_type'] ?? null) === 'unclassified') {
            $query->whereNull('client_type');
        } elseif ($filters['client_type'] ?? null) {
            $query->where('client_type', $filters['client_type']);
        }
        if (($filters['import_review'] ?? null) === '1') {
            $query->whereHas('importEntry', fn ($entry) => $entry->whereJsonLength('issues', '>', 0));
        }
        return $this->applySearch($query, $filters['search'] ?? '');
    }

    public function forSearch(string $search): Builder
    {
        return $this->applySearch($this->forDropdown(), $search);
    }

    public function forDropdown(): Builder
    {
        abort_unless(auth()->check(), 403);
        return Client::current()->visibleTo(auth()->user())->orderBy('name');
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        if (trim($search) === '') {
            return $query;
        }
        $searchStr = '%'.mb_strtolower(trim($search)).'%';
        return $query->where(function (Builder $matches) use ($searchStr) {
            foreach (['name', 'company_name', 'reference_person', 'email', 'phone', 'mobile', 'vat_number', 'tax_code'] as $field) {
                $matches->orWhereRaw("LOWER({$field}) LIKE ?", [$searchStr]);
            }
            $matches->orWhereHas('vehicles', fn ($vehicles) => $vehicles->whereRaw('LOWER(plate) LIKE ?', [preg_replace('/\s+/', '', $searchStr)]));
            $matches->orWhereHas('importEntry', fn ($entries) => $entries->whereRaw('LOWER(deferred_data) LIKE ?', [$searchStr]));
            $matches->orWhereHas('policies', fn ($policies) => $policies->whereRaw('LOWER(number) LIKE ?', [$searchStr]));
        });
    }
}
