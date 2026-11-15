<?php

namespace App\Http\Controllers;

use App\Models\Chamber;
use App\Models\Expense;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SecurityLog;
use App\Models\User;
use App\Support\ChamberContext;
use App\Support\MediaStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminConsoleController extends Controller
{
    public function index(): View
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $monthStart = $today->startOfMonth()->toDateString();
        $monthEnd = $today->toDateString();
        $monthLabel = localized_date($today, 'F Y');

        $chambers = Chamber::query()
            ->with(['users' => fn ($query) => $query->orderBy('name')])
            ->withCount('users')
            ->orderBy('name')
            ->get();
        $users = User::query()->with('chamber')->orderBy('name')->get();
        $securityLogs = SecurityLog::query()->latest()->limit(15)->get();

        $salesByChamber = Sale::query()
            ->withoutGlobalScope('chamber')
            ->select('chamber_id')
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total_amount), 0) as sales_revenue')
            ->where('status', 'completed')
            ->whereDate('sale_date', '>=', $monthStart)
            ->whereDate('sale_date', '<=', $monthEnd)
            ->groupBy('chamber_id')
            ->get()
            ->keyBy('chamber_id');
        $saleCostsByChamber = SaleItem::query()
            ->withoutGlobalScope('chamber')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereColumn('sales.chamber_id', 'sale_items.chamber_id')
            ->where('sales.status', 'completed')
            ->whereDate('sales.sale_date', '>=', $monthStart)
            ->whereDate('sales.sale_date', '<=', $monthEnd)
            ->groupBy('sale_items.chamber_id')
            ->selectRaw('sale_items.chamber_id, COALESCE(SUM(sale_items.line_cost_total), 0) as cogs, COALESCE(SUM(sale_items.quantity), 0) as units_sold')
            ->get()
            ->keyBy('chamber_id');
        $purchasesByChamber = Purchase::query()
            ->withoutGlobalScope('chamber')
            ->select('chamber_id')
            ->selectRaw('COUNT(*) as purchases_count, COALESCE(SUM(total_amount), 0) as purchases_total')
            ->where('status', 'completed')
            ->whereDate('purchase_date', '>=', $monthStart)
            ->whereDate('purchase_date', '<=', $monthEnd)
            ->groupBy('chamber_id')
            ->get()
            ->keyBy('chamber_id');
        $expensesByChamber = Expense::query()
            ->withoutGlobalScope('chamber')
            ->select('chamber_id')
            ->selectRaw('COUNT(*) as expenses_count, COALESCE(SUM(amount), 0) as expenses_total')
            ->where('status', 'completed')
            ->whereDate('expense_date', '>=', $monthStart)
            ->whereDate('expense_date', '<=', $monthEnd)
            ->groupBy('chamber_id')
            ->get()
            ->keyBy('chamber_id');
        $inventoryByChamber = Medicine::query()
            ->withoutGlobalScope('chamber')
            ->where('is_active', true)
            ->groupBy('chamber_id')
            ->selectRaw('chamber_id, COUNT(*) as medicine_count, COALESCE(SUM(stock_quantity), 0) as stock_units')
            ->get()
            ->keyBy('chamber_id');

        $chambers->each(function (Chamber $chamber) use ($salesByChamber, $saleCostsByChamber, $purchasesByChamber, $expensesByChamber, $inventoryByChamber): void {
            $sales = $salesByChamber->get($chamber->id);
            $saleCosts = $saleCostsByChamber->get($chamber->id);
            $purchases = $purchasesByChamber->get($chamber->id);
            $expenses = $expensesByChamber->get($chamber->id);
            $inventory = $inventoryByChamber->get($chamber->id);
            $revenue = (float) ($sales?->sales_revenue ?? 0);
            $grossProfit = $revenue - (float) ($saleCosts?->cogs ?? 0);
            $expenseTotal = (float) ($expenses?->expenses_total ?? 0);

            $chamber->setAttribute('monthly_sales_count', (int) ($sales?->sales_count ?? 0));
            $chamber->setAttribute('monthly_sales_revenue', $revenue);
            $chamber->setAttribute('monthly_purchases_count', (int) ($purchases?->purchases_count ?? 0));
            $chamber->setAttribute('monthly_purchases_total', (float) ($purchases?->purchases_total ?? 0));
            $chamber->setAttribute('monthly_expenses_count', (int) ($expenses?->expenses_count ?? 0));
            $chamber->setAttribute('monthly_expenses_total', $expenseTotal);
            $chamber->setAttribute('monthly_gross_profit', $grossProfit);
            $chamber->setAttribute('monthly_net_profit', $grossProfit - $expenseTotal);
            $chamber->setAttribute('monthly_units_sold', (int) ($saleCosts?->units_sold ?? 0));
            $chamber->setAttribute('medicine_count', (int) ($inventory?->medicine_count ?? 0));
            $chamber->setAttribute('stock_units', (int) ($inventory?->stock_units ?? 0));
        });

        $activeChamber = app(ChamberContext::class)->chamber();
        $overview = [
            'chambers' => $chambers->count(),
            'accounts' => $users->count(),
            'sales_count' => $chambers->sum('monthly_sales_count'),
            'sales_revenue' => $chambers->sum('monthly_sales_revenue'),
            'net_profit' => $chambers->sum('monthly_net_profit'),
        ];

        return view('admin.index', compact('chambers', 'users', 'securityLogs', 'activeChamber', 'overview', 'monthLabel'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin->isAdmin(), 403);

        $requiresCurrentPassword = $request->input('email') !== $admin->email || $request->filled('password');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->getKey())],
            'designation' => ['nullable', 'string', 'max:100'],
            'current_password' => [$requiresCurrentPassword ? 'required' : 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', 'min:12'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_image' => ['sometimes', 'boolean'],
        ]);

        $oldAvatar = $admin->avatar_path;
        if ($request->hasFile('profile_image')) {
            $newAvatar = $request->file('profile_image')->store(MediaStorage::AVATAR_DIRECTORY, config('filesystems.uploads'));
            if (! $newAvatar) {
                return back()->withErrors(['profile_image' => __('admin.image_store_error')])->withInput();
            }
            $admin->avatar_path = $newAvatar;
        } elseif ($request->boolean('remove_profile_image')) {
            $admin->avatar_path = null;
        }

        $admin->name = $data['name'];
        $admin->email = $data['email'];
        $admin->designation = $data['designation'] ?? null;
        if (! empty($data['password'])) {
            $admin->password = $data['password'];
        }
        $admin->save();

        if ($oldAvatar && $oldAvatar !== $admin->avatar_path && str_starts_with($oldAvatar, MediaStorage::AVATAR_DIRECTORY.'/')) {
            MediaStorage::disk()->delete($oldAvatar);
        }
        if (! empty($data['password'])) {
            $request->session()->regenerate();
            SecurityLog::record(SecurityLog::PASSWORD_CHANGED, 'Changed their own password.', $admin);
        } else {
            SecurityLog::record(SecurityLog::PROFILE_UPDATED, 'Updated their own profile.', $admin);
        }

        return back()->with('success', __('admin.profile_updated_success'));
    }

    public function activate(Request $request, Chamber $chamber): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        app(ChamberContext::class)->set($chamber->id);

        return redirect()->route('dashboard')->with('success', __('admin.now_managing', ['name' => $chamber->name]));
    }

    public function clearSecurityLog(): RedirectResponse
    {
        $deleted = SecurityLog::query()->delete();

        return redirect()->to(route('admin.index').'#security-log')
            ->with('success', __('admin.security_log_cleared', ['count' => localized_number($deleted)]));
    }
}
