<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const page = usePage();

const props = defineProps({
  unlocked:       { type: Boolean, required: true },
  hasPin:         { type: Boolean, required: true },
  ledgerType:     { type: String,  required: true },
  trialBalance:   { type: Object,  required: true },
  profitAndLoss:  { type: Object,  required: true },
  balanceSheet:   { type: Object,  required: true },
  startDate:      { type: String,  default: '' },
  endDate:        { type: String,  default: '' },
  lastClosedDate: { type: String,  default: null },
});

const activeTab            = ref('trial_balance');
const selectedLedgerType   = ref(props.ledgerType);
const filterStartDate      = ref(props.startDate || '');
const filterEndDate        = ref(props.endDate   || '');

const generateFinancialYears = () => {
  const startYear = 2021;
  const currentYear = new Date().getFullYear();
  const yearsList = [{ label: 'Custom Range', start: '', end: '' }];
  
  for (let year = startYear; year <= currentYear + 1; year++) {
    const nextYearShort = String(year + 1).slice(-2);
    const start = `${year}-04-01`;
    const end = `${year + 1}-03-31`;
    yearsList.push({
      label: `FY ${year}-${nextYearShort} (01/04/${year} - 31/03/${year + 1})`,
      start,
      end
    });
  }
  return yearsList;
};

const financialYears = generateFinancialYears();

const selectedFY = ref('Custom Range');

const matchFinancialYear = () => {
  const matched = financialYears.find(
    fy => fy.start === filterStartDate.value && fy.end === filterEndDate.value
  );
  selectedFY.value = matched ? matched.label : 'Custom Range';
};

matchFinancialYear();

const handleFYChange = () => {
  const fy = financialYears.find(f => f.label === selectedFY.value);
  if (fy && fy.label !== 'Custom Range') {
    filterStartDate.value = fy.start;
    filterEndDate.value = fy.end;
    applyFilters();
  }
};

const isFYClosed = computed(() => {
  if (!props.lastClosedDate) return false;
  const fy = financialYears.find(f => f.label === selectedFY.value);
  if (!fy || fy.label === 'Custom Range') return false;
  return fy.end <= props.lastClosedDate;
});

const closeFinancialYear = async () => {
  const fy = financialYears.find(f => f.label === selectedFY.value);
  if (!fy || fy.label === 'Custom Range') return;

  const result = await Swal.fire({
    title: 'Are you sure?',
    text: `Do you want to close and lock ${fy.label}? This will prevent any edits on or before ${fy.end}.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#2e2c92',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Close & Lock It!'
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post(route('reports.ledger.close-year'), {
        close_date: fy.end
      });
      Swal.fire('Closed!', response.data.message, 'success').then(() => {
        location.reload();
      });
    } catch (error) {
      Swal.fire('Error!', error.response?.data?.message || 'Failed to close financial year.', 'error');
    }
  }
};

// Format currency in INR
const formatCurrency = (val) =>
  new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }).format(val || 0);

const formatNum = (val) =>
  new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val || 0);

// Apply / clear date filters
const applyFilters = () => {
  router.get(route('reports.ledger'), {
    start_date:  filterStartDate.value,
    end_date:    filterEndDate.value,
  });
};

const clearFilters = () => {
  filterStartDate.value = '';
  filterEndDate.value   = '';
  router.get(route('reports.ledger'));
};

// Balance Sheet computed
const balanceDifference = computed(() =>
  Math.abs(props.balanceSheet.total_assets - props.balanceSheet.total_liabilities_and_equity)
);
const isBalanced = computed(() => balanceDifference.value < 0.01);

// Summary KPI cards
const netProfitLabel = computed(() =>
  props.profitAndLoss.net_profit >= 0 ? 'Net Profit' : 'Net Loss'
);
const profitIsPositive = computed(() => props.profitAndLoss.net_profit >= 0);

// Print current tab
const printReport = () => {
  window.print();
};

// Re-sync all ledger entries
const resyncing = ref(false);
const resyncLedger = () => {
  resyncing.value = true;
  router.post(route('reports.ledger.repost'), {}, {
    onFinish: () => { resyncing.value = false; }
  });
};

const flashSuccess = computed(() => page.props?.flash?.success || null);
</script>

<template>
  <Head title="Accounting Ledger & Financial Reports" />

  <AuthenticatedLayout>
    <div class="ledger-page">

      <!-- ══════════════ HEADER ══════════════ -->
      <div class="ledger-header">
        <div class="header-title-group">
          <div class="header-icon">📖</div>
          <div>
            <h1 class="header-title">
              Accounting Ledger
            </h1>
            <p class="header-sub">Trial Balance · Profit & Loss · Balance Sheet</p>
          </div>
        </div>

        <!-- Ledger Actions -->
        <div class="header-actions">
          <button @click="printReport" class="btn-print no-print">
            🖨️ Print
          </button>
          <button @click="resyncLedger" :disabled="resyncing" class="btn-resync no-print">
            {{ resyncing ? '⏳ Syncing...' : '🔄 Re-sync Ledger' }}
          </button>
        </div>
      </div>

      <!-- Flash Message -->
      <div v-if="flashSuccess" class="flash-success no-print">
        ✅ {{ flashSuccess }}
      </div>

      <!-- ══════════════ FILTERS ══════════════ -->
      <div class="filter-bar no-print">
        <div class="filter-group">
          <label class="filter-label">Financial Year</label>
          <select v-model="selectedFY" @change="handleFYChange" class="filter-input" style="background: white;">
            <option v-for="fy in financialYears" :key="fy.label" :value="fy.label">
              {{ fy.label }}
            </option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">From Date</label>
          <input type="date" v-model="filterStartDate" class="filter-input" />
        </div>
        <div class="filter-group">
          <label class="filter-label">To Date</label>
          <input type="date" v-model="filterEndDate" class="filter-input" />
        </div>
        <div class="filter-actions">
          <button @click="applyFilters" class="btn-primary">Apply Filter</button>
          <button @click="clearFilters" class="btn-ghost">Clear</button>
          <button
            v-if="selectedFY !== 'Custom Range' && !isFYClosed"
            @click="closeFinancialYear"
            type="button"
            class="btn-primary"
            style="background-color: #d97706;"
          >
            🔒 Close Year
          </button>
          <span
            v-else-if="selectedFY !== 'Custom Range' && isFYClosed"
            class="px-3 py-2 text-xs font-semibold rounded-lg bg-red-100 text-red-800 border border-red-200"
          >
            🔒 Year Closed
          </span>
        </div>
        <div v-if="startDate || endDate" class="filter-active-badge">
          <span>📅 Filtered: {{ startDate || '∞' }} → {{ endDate || 'Today' }}</span>
          <span v-if="lastClosedDate && endDate <= lastClosedDate" class="ml-2 px-2 py-0.5 text-xs font-bold rounded-full bg-red-200 text-red-800">
            🔒 Closed Period
          </span>
        </div>
      </div>

      <!-- ══════════════ REPORT CONTENT ══════════════ -->
      <div class="report-area">

        <!-- KPI Summary Cards -->
        <div class="kpi-grid no-print">
          <div class="kpi-card kpi-revenue">
            <div class="kpi-icon">💹</div>
            <div class="kpi-body">
              <span class="kpi-label">Total Revenue</span>
              <span class="kpi-value">{{ formatCurrency(profitAndLoss.total_revenue) }}</span>
            </div>
          </div>
          <div class="kpi-card kpi-expense">
            <div class="kpi-icon">📉</div>
            <div class="kpi-body">
              <span class="kpi-label">Total Expenses</span>
              <span class="kpi-value">{{ formatCurrency(profitAndLoss.total_expense) }}</span>
            </div>
          </div>
          <div class="kpi-card" :class="profitIsPositive ? 'kpi-profit' : 'kpi-loss'">
            <div class="kpi-icon">{{ profitIsPositive ? '🏆' : '⚠️' }}</div>
            <div class="kpi-body">
              <span class="kpi-label">{{ netProfitLabel }}</span>
              <span class="kpi-value">{{ formatCurrency(profitAndLoss.net_profit) }}</span>
            </div>
          </div>
          <div class="kpi-card kpi-assets">
            <div class="kpi-icon">🏛️</div>
            <div class="kpi-body">
              <span class="kpi-label">Total Assets</span>
              <span class="kpi-value">{{ formatCurrency(balanceSheet.total_assets) }}</span>
            </div>
          </div>
        </div>

        <!-- Tab Bar -->
        <div class="tab-bar no-print">
          <button @click="activeTab = 'trial_balance'"
            :class="['tab-btn', activeTab === 'trial_balance' ? 'tab-active' : '']">
            ⚖️ Trial Balance
          </button>
          <button @click="activeTab = 'profit_loss'"
            :class="['tab-btn', activeTab === 'profit_loss' ? 'tab-active' : '']">
            📈 Profit & Loss
          </button>
          <button @click="activeTab = 'balance_sheet'"
            :class="['tab-btn', activeTab === 'balance_sheet' ? 'tab-active' : '']">
            🏛️ Balance Sheet
          </button>
        </div>

        <!-- ── A. TRIAL BALANCE ── -->
        <div v-if="activeTab === 'trial_balance'" class="report-card">
          <!-- Print Header -->
          <div class="print-only report-print-header">
            <h2>Trial Balance</h2>
            <p v-if="startDate || endDate">Period: {{ startDate || '∞' }} to {{ endDate || 'Today' }}</p>
          </div>

          <div class="report-card-header">
            <div>
              <h2 class="report-title">⚖️ Trial Balance</h2>
              <p class="report-sub">Net debit/credit balances by account</p>
            </div>
            <div class="balance-badge" :class="Math.abs(trialBalance.total_debit - trialBalance.total_credit) < 0.01 ? 'badge-balanced' : 'badge-unbalanced'">
              <span v-if="Math.abs(trialBalance.total_debit - trialBalance.total_credit) < 0.01">✅ Balanced</span>
              <span v-else>⚠️ Unbalanced (Diff: {{ formatCurrency(Math.abs(trialBalance.total_debit - trialBalance.total_credit)) }})</span>
            </div>
          </div>

          <div class="table-wrap">
            <table class="ledger-table">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Account Name</th>
                  <th>Type</th>
                  <th class="text-right">Debit (Dr.)</th>
                  <th class="text-right">Credit (Cr.)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in trialBalance.items" :key="item.code">
                  <td class="code-cell">{{ item.code }}</td>
                  <td class="name-cell">{{ item.name }}</td>
                  <td>
                    <span class="type-badge" :class="`type-${item.type.toLowerCase()}`">{{ item.type }}</span>
                  </td>
                  <td class="amount-cell text-right debit-col">
                    {{ item.debit > 0 ? formatCurrency(item.debit) : '—' }}
                  </td>
                  <td class="amount-cell text-right credit-col">
                    {{ item.credit > 0 ? formatCurrency(item.credit) : '—' }}
                  </td>
                </tr>
                <tr v-if="trialBalance.items.length === 0">
                  <td colspan="5" class="empty-row">No transactions found. Try adjusting the date range.</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="totals-row">
                  <td colspan="3" class="text-right totals-label">TOTALS</td>
                  <td class="text-right totals-debit">{{ formatCurrency(trialBalance.total_debit) }}</td>
                  <td class="text-right totals-credit">{{ formatCurrency(trialBalance.total_credit) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- ── B. PROFIT & LOSS ── -->
        <div v-if="activeTab === 'profit_loss'" class="report-card">
          <div class="print-only report-print-header">
            <h2>Profit & Loss Statement</h2>
            <p v-if="startDate || endDate">Period: {{ startDate || '∞' }} to {{ endDate || 'Today' }}</p>
          </div>

          <div class="report-card-header">
            <div>
              <h2 class="report-title">📈 Profit & Loss Statement</h2>
              <p class="report-sub">Revenue and expenditure summary for the selected period</p>
            </div>
          </div>

          <div class="pl-layout">
            <!-- Revenues -->
            <div class="pl-section">
              <div class="pl-section-head revenue-head">
                <span>💹 REVENUE</span>
                <span>{{ formatCurrency(profitAndLoss.total_revenue) }}</span>
              </div>
              <div class="pl-rows">
                <div v-for="item in profitAndLoss.revenue_items" :key="item.code" class="pl-row">
                  <div class="pl-row-left">
                    <span class="pl-code">{{ item.code }}</span>
                    <span class="pl-name">{{ item.name }}</span>
                  </div>
                  <span class="pl-amount revenue-amount">{{ formatCurrency(item.balance) }}</span>
                </div>
                <div v-if="!profitAndLoss.revenue_items.length" class="pl-empty">No revenue entries recorded.</div>
              </div>
            </div>

            <!-- Divider arrow -->
            <div class="pl-arrow">▼</div>

            <!-- Expenses -->
            <div class="pl-section">
              <div class="pl-section-head expense-head">
                <span>📉 EXPENSES</span>
                <span>{{ formatCurrency(profitAndLoss.total_expense) }}</span>
              </div>
              <div class="pl-rows">
                <div v-for="item in profitAndLoss.expense_items" :key="item.code" class="pl-row">
                  <div class="pl-row-left">
                    <span class="pl-code">{{ item.code }}</span>
                    <span class="pl-name">{{ item.name }}</span>
                  </div>
                  <span class="pl-amount expense-amount">{{ formatCurrency(item.balance) }}</span>
                </div>
                <div v-if="!profitAndLoss.expense_items.length" class="pl-empty">No expense entries recorded.</div>
              </div>
            </div>

            <!-- Net Profit Banner -->
            <div class="net-banner" :class="profitIsPositive ? 'net-profit' : 'net-loss'">
              <div class="net-banner-left">
                <span class="net-label">{{ profitIsPositive ? '🏆 NET PROFIT' : '⚠️ NET LOSS' }}</span>
                <span class="net-formula">Revenue ({{ formatCurrency(profitAndLoss.total_revenue) }}) − Expenses ({{ formatCurrency(profitAndLoss.total_expense) }})</span>
              </div>
              <span class="net-value">{{ formatCurrency(profitAndLoss.net_profit) }}</span>
            </div>
          </div>
        </div>

        <!-- ── C. BALANCE SHEET ── -->
        <div v-if="activeTab === 'balance_sheet'" class="report-card">
          <div class="print-only report-print-header">
            <h2>Balance Sheet</h2>
            <p v-if="endDate">As of: {{ endDate }}</p>
          </div>

          <div class="report-card-header">
            <div>
              <h2 class="report-title">🏛️ Balance Sheet</h2>
              <p class="report-sub">Cumulative assets, liabilities & equity position{{ endDate ? ' as of ' + endDate : '' }}</p>
            </div>
            <div class="balance-badge" :class="isBalanced ? 'badge-balanced' : 'badge-unbalanced'">
              <span v-if="isBalanced">✅ Balanced</span>
              <span v-else>⚠️ Off by {{ formatCurrency(balanceDifference) }}</span>
            </div>
          </div>

          <div class="bs-layout">
            <!-- Left: Assets -->
            <div class="bs-col">
              <div class="bs-col-head assets-head">🏦 ASSETS</div>
              <div class="bs-rows">
                <div v-for="item in balanceSheet.asset_items" :key="item.code" class="bs-row">
                  <div class="bs-row-left">
                    <span class="pl-code">{{ item.code }}</span>
                    <span class="pl-name">{{ item.name }}</span>
                  </div>
                  <span class="pl-amount">{{ formatCurrency(item.balance) }}</span>
                </div>
                <div v-if="!balanceSheet.asset_items.length" class="pl-empty">No asset balances.</div>
              </div>
              <div class="bs-total assets-total">
                <span>Total Assets</span>
                <span>{{ formatCurrency(balanceSheet.total_assets) }}</span>
              </div>
            </div>

            <!-- Right: Liabilities + Equity -->
            <div class="bs-col">
              <!-- Liabilities -->
              <div class="bs-col-head liabilities-head">📋 LIABILITIES</div>
              <div class="bs-rows">
                <div v-for="item in balanceSheet.liability_items" :key="item.code" class="bs-row">
                  <div class="bs-row-left">
                    <span class="pl-code">{{ item.code }}</span>
                    <span class="pl-name">{{ item.name }}</span>
                  </div>
                  <span class="pl-amount">{{ formatCurrency(item.balance) }}</span>
                </div>
                <div v-if="!balanceSheet.liability_items.length" class="pl-empty">No liability balances.</div>
              </div>
              <div class="bs-subtotal">
                <span>Total Liabilities</span>
                <span>{{ formatCurrency(balanceSheet.total_liabilities) }}</span>
              </div>

              <!-- Equity -->
              <div class="bs-col-head equity-head mt-4">💎 EQUITY</div>
              <div class="bs-rows">
                <div v-for="item in balanceSheet.equity_items" :key="item.code" class="bs-row">
                  <div class="bs-row-left">
                    <span class="pl-code">{{ item.code }}</span>
                    <span class="pl-name">{{ item.name }}</span>
                  </div>
                  <span class="pl-amount">{{ formatCurrency(item.balance) }}</span>
                </div>
                <div v-if="!balanceSheet.equity_items.length" class="pl-empty">No equity entries.</div>
              </div>
              <div class="bs-total liabilities-equity-total">
                <span>Total Liabilities & Equity</span>
                <span>{{ formatCurrency(balanceSheet.total_liabilities_and_equity) }}</span>
              </div>
            </div>
          </div>

          <!-- Balance Check Footer -->
          <div v-if="!isBalanced" class="bs-alert">
            ⚠️ The balance sheet is out of balance by <strong>{{ formatCurrency(balanceDifference) }}</strong>. This may indicate missing journal entries or unposted transactions.
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
/* ══════════════ LAYOUT ══════════════ */
.ledger-page {
  max-width: 1280px;
  margin: 0 auto;
  padding: 1.5rem;
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* ══════════════ HEADER ══════════════ */
.ledger-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.header-title-group {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.header-icon {
  font-size: 2.5rem;
  line-height: 1;
}
.header-title {
  font-size: 1.75rem;
  font-weight: 800;
  color: #1e1b4b;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  margin: 0;
}
.header-sub {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0.25rem 0 0;
}
.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.private-badge {
  background: #fee2e2;
  color: #b91c1c;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
}

/* ══════════════ SWITCHER ══════════════ */
.ledger-switcher {
  display: flex;
  background: #f1f5f9;
  border-radius: 0.75rem;
  padding: 0.25rem;
  gap: 0.2rem;
}
.switcher-btn {
  padding: 0.5rem 1rem;
  border-radius: 0.6rem;
  font-size: 0.8rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  background: transparent;
  color: #64748b;
  transition: all 0.2s;
}
.switcher-active {
  background: white;
  color: #2e2c92;
  box-shadow: 0 1px 4px rgba(0,0,0,0.12);
}
.switcher-private-active {
  background: #dc2626;
  color: white;
  box-shadow: 0 1px 4px rgba(220,38,38,0.3);
}

/* ══════════════ PRINT BUTTON ══════════════ */
.btn-print {
  background: #1e1b4b;
  color: white;
  border: none;
  padding: 0.5rem 1.1rem;
  border-radius: 0.6rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-print:hover { background: #312e81; }

/* ══════════════ FILTERS ══════════════ */
.filter-bar {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 1rem;
  padding: 1rem 1.25rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: flex-end;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}
.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 180px;
}
.filter-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.filter-input {
  border: 1.5px solid #e5e7eb;
  border-radius: 0.6rem;
  padding: 0.45rem 0.75rem;
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.2s;
}
.filter-input:focus { border-color: #2e2c92; }
.filter-actions { display: flex; gap: 0.5rem; align-items: flex-end; }
.filter-active-badge {
  background: #ede9fe;
  color: #5b21b6;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 0.35rem 0.85rem;
  border-radius: 999px;
  align-self: flex-end;
}

/* ══════════════ BUTTONS ══════════════ */
.btn-primary {
  background: #2e2c92;
  color: white;
  border: none;
  padding: 0.5rem 1.1rem;
  border-radius: 0.65rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  transition: background 0.2s;
}
.btn-primary:hover { background: #1f1d6b; }
.btn-ghost {
  background: #f1f5f9;
  color: #374151;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 0.65rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-ghost:hover { background: #e2e8f0; }

/* ══════════════ PIN SCREEN ══════════════ */
.pin-screen {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 55vh;
  padding: 2rem 0;
}
.pin-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 1.5rem;
  padding: 2.5rem 2rem;
  max-width: 380px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(0,0,0,0.08);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}
.pin-icon-wrap {
  width: 72px; height: 72px;
  background: linear-gradient(135deg, #fef2f2, #fee2e2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.pin-lock-icon { font-size: 2.25rem; }
.pin-title { font-size: 1.4rem; font-weight: 800; color: #1f2937; margin: 0; }
.pin-desc { color: #6b7280; font-size: 0.875rem; text-align: center; }
.pin-no-setup { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
.pin-entry { display: flex; flex-direction: column; align-items: center; gap: 1rem; width: 100%; }
.pin-dots { display: flex; gap: 1rem; }
.pin-dot {
  width: 14px; height: 14px;
  border-radius: 50%;
  border: 2px solid #d1d5db;
  transition: all 0.15s;
}
.pin-dot-filled {
  background: #2e2c92;
  border-color: #2e2c92;
  transform: scale(1.15);
  box-shadow: 0 2px 6px rgba(46,44,146,0.35);
}
.pin-error {
  color: #dc2626;
  font-size: 0.82rem;
  font-weight: 600;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 0.5rem;
  padding: 0.4rem 0.9rem;
}
.numpad {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
  width: 100%;
  max-width: 220px;
}
.numpad-key {
  width: 100%;
  aspect-ratio: 1;
  border-radius: 50%;
  border: none;
  background: #f8fafc;
  font-size: 1.1rem;
  font-weight: 700;
  color: #1f2937;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.numpad-key:hover:not(:disabled) { background: #e0e7ff; color: #2e2c92; }
.numpad-key:disabled { opacity: 0.5; cursor: not-allowed; }
.numpad-aux {
  width: 100%;
  aspect-ratio: 1;
  border-radius: 50%;
  border: none;
  background: transparent;
  font-size: 0.75rem;
  font-weight: 700;
  color: #9ca3af;
  cursor: pointer;
  transition: color 0.15s;
}
.numpad-aux:hover:not(:disabled) { color: #374151; }
.pin-loading { color: #6b7280; font-size: 0.82rem; font-style: italic; }

/* ══════════════ KPI CARDS ══════════════ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.kpi-card {
  border-radius: 1rem;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.kpi-revenue  { background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 1px solid #a7f3d0; }
.kpi-expense  { background: linear-gradient(135deg, #fff7ed, #fed7aa); border: 1px solid #fdba74; }
.kpi-profit   { background: linear-gradient(135deg, #eff6ff, #bfdbfe); border: 1px solid #93c5fd; }
.kpi-loss     { background: linear-gradient(135deg, #fef2f2, #fecaca); border: 1px solid #f87171; }
.kpi-assets   { background: linear-gradient(135deg, #f5f3ff, #ede9fe); border: 1px solid #c4b5fd; }
.kpi-icon { font-size: 1.75rem; }
.kpi-body { display: flex; flex-direction: column; gap: 0.2rem; }
.kpi-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
.kpi-value { font-size: 1.1rem; font-weight: 800; color: #1f2937; }

/* ══════════════ TABS ══════════════ */
.tab-bar {
  display: flex;
  gap: 0;
  border-bottom: 2px solid #e5e7eb;
  margin-bottom: 1.5rem;
}
.tab-btn {
  padding: 0.75rem 1.5rem;
  font-size: 0.9rem;
  font-weight: 700;
  border: none;
  background: transparent;
  color: #9ca3af;
  cursor: pointer;
  border-bottom: 3px solid transparent;
  margin-bottom: -2px;
  transition: all 0.2s;
}
.tab-btn:hover { color: #374151; }
.tab-active { color: #2e2c92; border-bottom-color: #2e2c92; }

/* ══════════════ REPORT CARD ══════════════ */
.report-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 1.25rem;
  padding: 1.75rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  animation: slideUp 0.35s ease-out;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.report-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.report-title { font-size: 1.2rem; font-weight: 800; color: #1e1b4b; margin: 0; }
.report-sub   { font-size: 0.82rem; color: #6b7280; margin: 0.2rem 0 0; }

/* ══════════════ BALANCE BADGE ══════════════ */
.balance-badge {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 0.35rem 0.9rem;
  border-radius: 999px;
}
.badge-balanced   { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
.badge-unbalanced { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }

/* ══════════════ TABLE ══════════════ */
.table-wrap { overflow-x: auto; border-radius: 0.75rem; border: 1px solid #f1f5f9; }
.ledger-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.ledger-table thead tr {
  background: #f8fafc;
}
.ledger-table th {
  padding: 0.85rem 1rem;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
  text-align: left;
  border-bottom: 1px solid #e5e7eb;
}
.ledger-table tbody tr {
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.15s;
}
.ledger-table tbody tr:hover { background: #fafafa; }
.ledger-table td {
  padding: 0.75rem 1rem;
  color: #374151;
}
.code-cell { font-family: monospace; font-weight: 600; color: #6b7280; font-size: 0.82rem; }
.name-cell { font-weight: 600; color: #1f2937; }
.amount-cell { font-weight: 700; }
.debit-col  { color: #1d4ed8; }
.credit-col { color: #059669; }
.text-right { text-align: right; }
.empty-row  { text-align: center; color: #9ca3af; padding: 3rem 1rem; font-style: italic; }
.totals-row td { padding: 0.9rem 1rem; background: #f8fafc; border-top: 2px solid #e5e7eb; font-weight: 800; }
.totals-label  { color: #6b7280; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; }
.totals-debit  { color: #1d4ed8; font-size: 1rem; border-bottom: 3px double #1d4ed8; }
.totals-credit { color: #059669; font-size: 1rem; border-bottom: 3px double #059669; }

/* ══════════════ TYPE BADGES ══════════════ */
.type-badge { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 999px; }
.type-asset     { background: #eff6ff; color: #1d4ed8; }
.type-liability { background: #f5f3ff; color: #6d28d9; }
.type-equity    { background: #fef9c3; color: #92400e; }
.type-revenue   { background: #ecfdf5; color: #065f46; }
.type-expense   { background: #fff1f2; color: #be123c; }

/* ══════════════ P&L LAYOUT ══════════════ */
.pl-layout { display: flex; flex-direction: column; gap: 1.5rem; max-width: 860px; }
.pl-section { border: 1px solid #e5e7eb; border-radius: 0.85rem; overflow: hidden; }
.pl-section-head {
  display: flex; justify-content: space-between;
  padding: 0.85rem 1.25rem;
  font-size: 0.78rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.06em;
}
.revenue-head { background: #ecfdf5; color: #065f46; }
.expense-head { background: #fff7ed; color: #92400e; }
.pl-rows { padding: 0.5rem 0; }
.pl-row {
  display: flex; justify-content: space-between;
  align-items: center; padding: 0.6rem 1.25rem;
  transition: background 0.15s; font-size: 0.875rem;
}
.pl-row:hover { background: #f9fafb; }
.pl-row-left  { display: flex; align-items: center; gap: 0.75rem; }
.pl-code      { font-family: monospace; font-size: 0.75rem; color: #9ca3af; font-weight: 600; }
.pl-name      { font-weight: 600; color: #374151; }
.pl-amount    { font-weight: 700; color: #1f2937; }
.revenue-amount { color: #065f46; }
.expense-amount { color: #b91c1c; }
.pl-empty     { color: #9ca3af; font-style: italic; font-size: 0.82rem; padding: 0.75rem 1.25rem; }
.pl-arrow     { text-align: center; font-size: 1.5rem; color: #d1d5db; }

.net-banner {
  display: flex; justify-content: space-between;
  align-items: center; border-radius: 1rem;
  padding: 1.5rem; color: white; flex-wrap: wrap; gap: 1rem;
}
.net-profit { background: linear-gradient(135deg, #059669, #047857); }
.net-loss   { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.net-banner-left { display: flex; flex-direction: column; gap: 0.3rem; }
.net-label   { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.85; }
.net-formula { font-size: 0.82rem; opacity: 0.75; }
.net-value   { font-size: 1.75rem; font-weight: 900; border-bottom: 3px double rgba(255,255,255,0.5); padding-bottom: 2px; }

/* ══════════════ BALANCE SHEET ══════════════ */
.bs-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
@media (max-width: 768px) { .bs-layout { grid-template-columns: 1fr; } }
.bs-col { display: flex; flex-direction: column; gap: 0.5rem; }
.bs-col-head {
  font-size: 0.75rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.07em;
  padding: 0.65rem 1rem; border-radius: 0.6rem;
}
.assets-head       { background: #eff6ff; color: #1e40af; }
.liabilities-head  { background: #f5f3ff; color: #5b21b6; }
.equity-head       { background: #fef9c3; color: #78350f; }
.bs-rows { border: 1px solid #e5e7eb; border-radius: 0.6rem; overflow: hidden; }
.bs-row {
  display: flex; justify-content: space-between;
  align-items: center; padding: 0.6rem 1rem;
  font-size: 0.875rem; border-bottom: 1px solid #f1f5f9;
  transition: background 0.15s;
}
.bs-row:last-child { border-bottom: none; }
.bs-row:hover { background: #f9fafb; }
.bs-subtotal {
  display: flex; justify-content: space-between;
  padding: 0.6rem 1rem;
  font-size: 0.82rem; font-weight: 700;
  background: #f8fafc; border-radius: 0.6rem;
  border: 1px solid #e5e7eb; color: #374151;
}
.bs-total {
  display: flex; justify-content: space-between;
  padding: 1rem 1.25rem;
  font-weight: 800; color: white;
  border-radius: 0.75rem;
  font-size: 0.95rem; margin-top: auto;
}
.assets-total              { background: linear-gradient(135deg, #1d4ed8, #1e40af); }
.liabilities-equity-total  { background: linear-gradient(135deg, #374151, #111827); }
.mt-4 { margin-top: 1.25rem; }

.bs-alert {
  margin-top: 1.25rem;
  background: #fef9c3;
  border: 1px solid #fde047;
  border-radius: 0.75rem;
  padding: 0.9rem 1.25rem;
  font-size: 0.85rem;
  color: #78350f;
}

/* ══════════════ PRINT STYLES ══════════════ */
.print-only { display: none; }
.report-print-header h2 { font-size: 1.4rem; margin: 0 0 0.25rem; color: #1e1b4b; }
.report-print-header p  { color: #6b7280; font-size: 0.85rem; margin: 0; }

/* ══════════════ RESYNC BUTTON ══════════════ */
.btn-resync {
  background: #f0fdf4;
  color: #166534;
  border: 1.5px solid #86efac;
  padding: 0.5rem 1rem;
  border-radius: 0.65rem;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-resync:hover:not(:disabled) { background: #dcfce7; }
.btn-resync:disabled { opacity: 0.6; cursor: not-allowed; }

/* ══════════════ FLASH MESSAGE ══════════════ */
.flash-success {
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 0.75rem;
  padding: 0.85rem 1.25rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #166534;
  margin-bottom: 1rem;
}

@media print {
  .no-print { display: none !important; }
  .print-only { display: block; margin-bottom: 1rem; }
  .ledger-page { padding: 0; }
  .report-card { box-shadow: none; border: none; padding: 0; }
  .ledger-table th, .ledger-table td { padding: 0.5rem 0.75rem; }
  .net-banner, .bs-total, .assets-total, .liabilities-equity-total {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>
