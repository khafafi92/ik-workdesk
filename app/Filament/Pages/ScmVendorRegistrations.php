<?php

namespace App\Filament\Pages;

use App\Models\Scm\VendorRegistration;
use App\Services\Scm\Access;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use UnitEnum;

class ScmVendorRegistrations extends Page
{
    use WithPagination;

    protected static ?string $slug = 'scm/data-vendor';

    protected static ?string $title = 'Data Vendor';

    protected static ?string $navigationLabel = 'Data Vendor';

    protected static string|UnitEnum|null $navigationGroup = 'SCM';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.scm-vendor-registrations';

    public const STATUSES = ['draft' => 'Draft / belum dikirim', 'submitted' => 'Menunggu pemeriksaan',
        'correction' => 'Perlu perbaikan', 'accepted' => 'Diterima SCM', 'revoked' => 'Akses dicabut'];

    #[Url(as: 'cari')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'perusahaan')]
    public string $companyFilter = '';

    public static function canAccess(): bool
    {
        return Access::manages(auth()->user());
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'companyFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'statusFilter', 'companyFilter');
        $this->resetPage();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $latestPublic = VendorRegistration::selectRaw('MAX(id)')->whereNotNull('portal_access_id')->groupBy('portal_access_id');
        $latestInvited = VendorRegistration::selectRaw('MAX(id)')->whereNull('portal_access_id')->whereNotNull('vendor_id')->groupBy('vendor_id');
        $query = VendorRegistration::with(['vendor', 'portalAccess'])->withCount('files')
            ->where(fn (Builder $query) => $query->whereIn('id', $latestPublic)->orWhereIn('id', $latestInvited)
                ->orWhere(fn (Builder $query) => $query->whereNull('portal_access_id')->whereNull('vendor_id')));
        $term = mb_substr(trim($this->search), 0, 255);
        if ($term !== '') {
            $name = DB::getDriverName() === 'pgsql' ? DB::raw("answers->'1'->>'name'") : 'answers->1->name';
            $email = DB::getDriverName() === 'pgsql' ? DB::raw("answers->'1'->>'email'") : 'answers->1->email';
            $query->where(fn (Builder $query) => $query->whereLike($name, '%'.$term.'%')
                ->orWhereLike($email, '%'.$term.'%')
                ->orWhereHas('vendor', fn (Builder $query) => $query->whereLike('name', '%'.$term.'%')->orWhereLike('code', '%'.$term.'%')));
        }
        if ($this->companyFilter !== '') {
            $query->where('company', $this->companyFilter);
        }
        if ($this->statusFilter === 'revoked') {
            $query->where(fn (Builder $query) => $query->whereNotNull('revoked_at')->orWhereHas('portalAccess', fn (Builder $query) => $query->whereNotNull('revoked_at')));
        } elseif ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter)->whereNull('revoked_at')
                ->whereDoesntHave('portalAccess', fn (Builder $query) => $query->whereNotNull('revoked_at'));
        }

        return ['registrations' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(15),
            'statuses' => self::STATUSES, 'companies' => collect(config('scm.companies'))->map(fn ($company) => $company['name'])->all()];
    }
}
