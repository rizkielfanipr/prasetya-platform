<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AuditLogView from '@/features/audit/AuditLogView.vue';
import { routes } from '@/lib/routes';
import type { AuditEntry, AuditFilters, Paginated } from '@/types/backoffice';

// The old Audit Log page (every entry), kept during the ADR-0010 transition. See Pengaturan → Aktivitas Sistem.
defineProps<{ filters: AuditFilters; logs: Paginated<AuditEntry> }>();
</script>

<template>
    <AppLayout title="Audit Log" description="Catatan tindakan penting, tidak dapat diubah">
        <AuditLogView :filters="filters" :logs="logs" :path="routes.auditLog" />
    </AppLayout>
</template>
