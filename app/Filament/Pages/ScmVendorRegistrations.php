<?php

namespace App\Filament\Pages;

use App\Http\Middleware\VendorPortalResponse;
use App\Models\Scm\VendorRegistration;
use App\Services\Scm\Access;
use App\Services\Scm\VendorPublicPortal;
use Filament\Notifications\Notification;
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

    protected static string|UnitEnum|null $navigationGroup = 'Pengadaan';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.scm-vendor-registrations';

    protected static string|array $routeMiddleware = [VendorPortalResponse::class];

    public const STATUSES = ['draft' => 'Draft / belum dikirim', 'submitted' => 'Terkirim / menunggu pemeriksaan',
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

    public static function directoryFilters(array $input): array
    {
        $filters = [];
        if (is_string($input['cari'] ?? null) && trim($input['cari']) !== '') {
            $filters['cari'] = mb_substr($input['cari'], 0, 255);
        }
        if (is_string($input['status'] ?? null) && ($input['status'] === 'ready' || isset(self::STATUSES[$input['status']]))) {
            $filters['status'] = $input['status'];
        }
        if (is_string($input['perusahaan'] ?? null) && isset(config('scm.companies')[$input['perusahaan']])) {
            $filters['perusahaan'] = $input['perusahaan'];
        }
        $page = filter_var($input['page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => intdiv(PHP_INT_MAX, 15)]]);
        if ($page !== false) {
            $filters['page'] = $page;
        }

        return $filters;
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

    public function reissueVendorId(int $registrationId, int $version): void
    {
        app(VendorPublicPortal::class)->reissueAccessCode(auth()->user(), $registrationId, $version);
        Notification::make()->title('Vendor ID pengganti diterbitkan. ID dan tautan formulir lama tidak berlaku.')->success()->send();
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
            $formId = preg_match('/^(?:Form\s*)?#?([1-9][0-9]*)$/iD', $term, $matches) ? filter_var($matches[1], FILTER_VALIDATE_INT) : null;
            $name = DB::getDriverName() === 'pgsql' ? DB::raw("answers->'1'->>'name'") : 'answers->1->name';
            $email = DB::getDriverName() === 'pgsql' ? DB::raw("answers->'1'->>'email'") : 'answers->1->email';
            $query->where(fn (Builder $query) => $query->whereLike($name, '%'.$term.'%')
                ->orWhereLike($email, '%'.$term.'%')
                ->when($formId, fn (Builder $query) => $query->orWhere('id', $formId))
                ->orWhereHas('vendor', fn (Builder $query) => $query->whereLike('name', '%'.$term.'%')->orWhereLike('code', '%'.$term.'%')));
        }
        if ($this->companyFilter !== '') {
            $query->where('company', $this->companyFilter);
        }
        if ($this->statusFilter === 'revoked') {
            $query->where(fn (Builder $query) => $query->whereNotNull('revoked_at')->orWhereHas('portalAccess', fn (Builder $query) => $query->whereNotNull('revoked_at')));
        } elseif ($this->statusFilter !== '') {
            $ready = $this->statusFilter === 'ready';
            $query->where('status', $ready ? 'submitted' : $this->statusFilter)->whereNull('revoked_at')
                ->when($ready, fn (Builder $query) => $query->where('expires_at', '>', now()))
                ->whereDoesntHave('portalAccess', fn (Builder $query) => $query->whereNotNull('revoked_at'));
        }

        $registrations = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(15);
        $directoryFilters = self::directoryFilters([
            'cari' => $this->search, 'status' => $this->statusFilter,
            'perusahaan' => $this->companyFilter, 'page' => $registrations->currentPage(),
        ]);

        return ['registrations' => $registrations, 'directoryFilters' => $directoryFilters,
            'masterVendorPage' => ScmProcurement::masterVendorPage(auth()->user()),
            'canManageVendorId' => auth()->user()->isSystemAdministrator(),
            'statuses' => self::STATUSES, 'companies' => collect(config('scm.companies'))->map(fn ($company) => $company['name'])->all()];
    }
}
