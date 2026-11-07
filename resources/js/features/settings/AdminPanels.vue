<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { formatDateTime, formatNumber } from '@/lib/format';
import type { StatusDefinition } from '@/lib/status';
import type { SettingsAdmin } from '@/types/backoffice';
import { Ban, CheckCircle2, Clock } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ admin: SettingsAdmin }>();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Nama' },
    { key: 'username', label: 'Username' },
    { key: 'active', label: 'Status' },
    { key: 'lastLogin', label: 'Masuk terakhir' },
];

const active: StatusDefinition = { label: 'Aktif', tone: 'success', icon: CheckCircle2 };
const inactive: StatusDefinition = { label: 'Nonaktif', tone: 'neutral', icon: Ban };

const gatewayStatus = computed<StatusDefinition>(() => {
    if (props.admin.gateway.driver === 'fake') {
        return { label: 'Gateway fake (bukan pembayaran asli)', tone: 'warning', icon: Clock };
    }

    return props.admin.gateway.configured
        ? { label: 'Kredensial terpasang', tone: 'success', icon: CheckCircle2 }
        : { label: 'Kredensial belum diisi', tone: 'danger', icon: Ban };
});
</script>

<template>
    <PanelCard title="Koneksi gateway" description="Hanya status. Kredensial tidak pernah ditampilkan.">
        <dl class="grid gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Status</dt>
                <dd class="mt-1" data-testid="gateway-status"><StatusBadge :status="gatewayStatus" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Lingkungan</dt>
                <dd class="mt-1">{{ admin.gateway.environment }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Path notifikasi</dt>
                <dd class="mt-1 break-all">
                    <code class="text-xs">{{ admin.gateway.notificationPath }}</code>
                </dd>
            </div>
        </dl>
    </PanelCard>

    <PanelCard title="Konfigurasi aplikasi">
        <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Lingkungan</dt>
                <dd class="mt-1">{{ admin.application.environment }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Zona waktu bisnis</dt>
                <dd class="mt-1">{{ admin.application.timezone }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Ambang menunggu terlalu lama</dt>
                <dd class="mt-1">{{ admin.application.pendingThresholdMinutes }} menit</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Job gagal</dt>
                <dd class="mt-1 tabular">{{ formatNumber(admin.application.failedJobs) }}</dd>
            </div>
            <div class="sm:col-span-2 xl:col-span-4">
                <dt class="text-xs font-semibold text-muted-foreground">Origin API yang diizinkan</dt>
                <dd class="mt-1 break-all">{{ admin.application.allowedOrigins.join(', ') }}</dd>
            </div>
        </dl>
    </PanelCard>

    <PanelCard title="Pengguna admin" description="Akun baru dibuat lewat php artisan platform:create-admin." flush>
        <DataTable caption="Pengguna admin" :columns="columns" :rows="admin.users" :row-key="(row) => row.id">
            <template #cell-name="{ row }"
                ><span class="whitespace-nowrap">{{ row.name }}</span></template
            >
            <template #cell-username="{ row }">{{ row.username }}</template>
            <template #cell-active="{ row }"><StatusBadge :status="row.isActive ? active : inactive" /></template>
            <template #cell-lastLogin="{ row }"
                ><span class="whitespace-nowrap">{{ formatDateTime(row.lastLoginAt) }}</span></template
            >
        </DataTable>
    </PanelCard>
</template>
