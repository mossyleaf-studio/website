<script setup>
import { CalendarDays, ExternalLink, Image, Scale, Link2, LogOut, Package, ShoppingBag, Truck, Type } from '@lucide/vue';
import { ConfigProvider, TooltipProvider } from 'reka-ui';
import { nextTick, useTemplateRef, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import ToastHost from '../components/ui/ToastHost.vue';
import PublishBar from './components/PublishBar.vue';
import { useSession } from '../composables/useSession.js';
import { intlLocale } from '../i18n/locale.js';

const { t } = useI18n();
const session = useSession();

const menu = useTemplateRef('menu');
const route = useRoute();

function revealActiveLink() {
    const active = menu.value?.querySelector('.router-link-active');
    if (!active || menu.value.scrollWidth <= menu.value.clientWidth) return;
    const link = active.getBoundingClientRect();
    menu.value.scrollLeft += link.left - menu.value.getBoundingClientRect().left - (menu.value.clientWidth - link.width) / 2;
}

watch(
    () => route.name,
    async () => {
        await nextTick();
        revealActiveLink();
    },
    { immediate: true, flush: 'post' },
);

const GROUPS = [
    {
        name: 'shop',
        sections: [
            { name: 'orders', icon: ShoppingBag },
            { name: 'products', icon: Package },
            { name: 'conventions', icon: CalendarDays },
            { name: 'settings', icon: Truck },
        ],
    },
    {
        name: 'site',
        sections: [
            { name: 'texts', icon: Type },
            { name: 'links', icon: Link2 },
            { name: 'images', icon: Image },
            { name: 'legal', icon: Scale },
        ],
    },
];
</script>

<template>
    <ConfigProvider :locale="intlLocale()">
        <TooltipProvider :delay-duration="300">
            <div class="admin">
                <aside class="admin__sidebar">
                    <RouterLink class="admin__brand" to="/">mossyleaf.studio</RouterLink>
                    <nav ref="menu" class="admin__menu" :aria-label="t('admin.nav.label')">
                        <div v-for="group in GROUPS" :key="group.name" class="admin__group">
                            <p :id="`admin-group-${group.name}`" class="admin__group-label">{{ t(`admin.nav.groups.${group.name}`) }}</p>
                            <ul class="admin__links" :aria-labelledby="`admin-group-${group.name}`">
                                <li v-for="section in group.sections" :key="section.name">
                                    <RouterLink v-slot="{ href, navigate, isActive }" :to="{ name: section.name }" custom>
                                        <a :href="href" :class="['admin__link', { 'router-link-active': isActive }]" :aria-current="isActive ? 'page' : undefined" @click="navigate">
                                            <component :is="section.icon" class="admin__icon" size="1.125rem" :stroke-width="1.75" aria-hidden="true" />
                                            {{ t(`admin.nav.${section.name}`) }}
                                        </a>
                                    </RouterLink>
                                </li>
                            </ul>
                        </div>
                    </nav>
                    <div class="admin__account">
                        <p v-if="session?.email" class="admin__email" :title="session.email">{{ session.email }}</p>
                        <a class="admin__account-link" href="/" target="_blank" rel="noopener">
                            <ExternalLink size="1rem" :stroke-width="1.75" aria-hidden="true" />
                            <span class="admin__account-label">{{ t('admin.nav.site') }}</span>
                        </a>
                        <form method="post" action="/logout">
                            <input type="hidden" name="_csrf_token" :value="session?.logoutToken" />
                            <button class="admin__account-link" type="submit">
                                <LogOut size="1rem" :stroke-width="1.75" aria-hidden="true" />
                                <span class="admin__account-label">{{ t('admin.nav.signOut') }}</span>
                            </button>
                        </form>
                    </div>
                </aside>
                <main id="main" class="admin__main">
                    <PublishBar>
                        <div class="admin__page">
                            <RouterView />
                        </div>
                    </PublishBar>
                </main>
            </div>
            <ToastHost />
        </TooltipProvider>
    </ConfigProvider>
</template>

<style scoped>
.admin {
    display: grid;
    grid-template-columns: var(--rail-width) minmax(0, 1fr);
    align-content: start;
    min-height: 100vh;
}

.admin__sidebar {
    position: sticky;
    top: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
    gap: var(--space-6);
    padding: var(--space-6) var(--space-4);
    background: var(--color-sidebar);
    border-right: 0.0625rem solid var(--color-border);
}

.admin__brand {
    padding: 0 var(--space-3);
    font-family: var(--font-display);
    font-size: 1.4rem;
    line-height: 1;
    color: var(--color-ink);
    text-decoration: none;
    white-space: nowrap;
}

.admin__menu { display: flex; flex: 1; flex-direction: column; gap: var(--space-4); min-height: 0; overflow-y: auto; }
.admin__group { display: flex; flex-direction: column; gap: var(--space-1); }
.admin__group-label { padding: 0 var(--space-3); color: var(--color-subtle); font-size: var(--font-size-xs); font-weight: 600; }
.admin__links { display: flex; flex-direction: column; gap: var(--space-1); margin: 0; padding: 0; list-style: none; }

.admin__link {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-2) var(--space-3);
    border-left: 0.125rem solid transparent;
    font-weight: 500;
    color: var(--color-muted);
    text-decoration: none;
    white-space: nowrap;
    transition: color var(--transition), background var(--transition), border-color var(--transition);
}

.admin__link:hover { color: var(--color-ink); background: var(--color-bg); }

.admin__link.router-link-active {
    color: var(--color-ink);
    background: var(--color-accent-soft);
    border-left-color: var(--color-accent);
}

.admin__icon { flex-shrink: 0; color: var(--color-subtle); transition: color var(--transition); }
.admin__link:hover .admin__icon { color: var(--color-ink); }
.admin__link.router-link-active .admin__icon { color: var(--color-accent); }

.admin__account {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-2);
    padding: var(--space-4) var(--space-3) 0;
    border-top: 0.0625rem solid var(--color-border);
    font-size: var(--font-size-sm);
}

.admin__email { max-width: 100%; overflow: hidden; color: var(--color-subtle); text-overflow: ellipsis; white-space: nowrap; }

.admin__account-link {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: 0;
    border: none;
    background: none;
    color: var(--color-muted);
    font-size: var(--font-size-sm);
    text-decoration: none;
    cursor: pointer;
    transition: color var(--transition);
}

.admin__account-link:hover { color: var(--color-ink); }

.admin__main {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    grid-template-areas:
        "publish"
        "list"
        "page";
    align-content: start;
    min-width: 0;
    overflow-x: clip;
    padding: var(--space-6) var(--space-7);
}

.admin__page { grid-area: page; min-width: 0; }

@media (min-width: 90rem) {
    .admin__main {
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            "publish publish"
            "page list";
        align-items: start;
    }
}

@media (max-width: 53.75rem) {
    .admin { grid-template-columns: minmax(0, 1fr); }

    .admin__sidebar {
        position: static;
        height: auto;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: var(--space-2) var(--space-3);
        padding: var(--space-3) var(--space-4) 0;
        border-right: none;
        border-bottom: 0.0625rem solid var(--color-border);
    }

    .admin__menu { grid-column: 1 / -1; grid-row: 2; flex-direction: row; gap: var(--space-1); min-width: 0; overflow-x: auto; overflow-y: visible; scrollbar-width: none; }
    .admin__group,
    .admin__links { flex-direction: row; }
    .admin__group-label { display: none; }
    .admin__link { border-left: none; border-bottom: 0.125rem solid transparent; padding: var(--space-2); }
    .admin__link.router-link-active { border-bottom-color: var(--color-accent); }
    .admin__account { grid-column: 2; grid-row: 1; flex-direction: row; align-items: center; gap: var(--space-3); padding: 0; border-top: none; }
    .admin__email { display: none; }
    .admin__main { padding: var(--space-4); }
}

@media (max-width: 30rem) {
    .admin__account-label {
        position: absolute;
        width: 0.0625rem;
        height: 0.0625rem;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }
}
</style>
