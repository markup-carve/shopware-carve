import template from './sw-cms-el-carve.html.twig';
import { carveToHtml } from '@markup-carve/carve';

Shopware.Component.register('sw-cms-el-carve', {
    template,
    mixins: [Shopware.Mixin.getByName('cms-element')],
    data() {
        return {
            livePreviewEnabled: true,
            allowRawHtml: false,
            includesConfigured: false,
            serverHtml: null,
        };
    },
    created() {
        this.initElementConfig('carve');
        Shopware.Service('systemConfigApiService')
            .getValues('ShopwareCarve.config')
            .then((values) => {
                const lp = values['ShopwareCarve.config.livePreview'];
                this.livePreviewEnabled = lp === undefined ? true : Boolean(lp);
                const raw = values['ShopwareCarve.config.allowRawHtml'];
                this.allowRawHtml = raw === undefined ? false : Boolean(raw);
                const root = values['ShopwareCarve.config.includeRoot'];
                this.includesConfigured = typeof root === 'string' && root.trim() !== '';
                this.refreshServerPreview();
            });
    },
    computed: {
        source() {
            return this.element?.config?.content?.value ?? '';
        },
        html() {
            // carve-js has no filesystem, so it can only show what a directive
            // looks like, never what it resolves to. Once a containment root is
            // configured the preview comes from the same server path the
            // storefront renders, under this user's own privileges.
            if (this.includesConfigured) {
                return this.serverHtml ?? '';
            }
            try {
                return carveToHtml(this.source, { allowRawHtml: this.allowRawHtml });
            } catch (e) {
                return '';
            }
        },
    },
    watch: {
        source() {
            this.refreshServerPreview();
        },
    },
    methods: {
        refreshServerPreview() {
            if (!this.includesConfigured) {
                return;
            }
            Shopware.Service('syncService')
                .httpClient.post('/_action/carve/preview', { source: this.source })
                .then((response) => {
                    this.serverHtml = response?.data?.html ?? '';
                })
                .catch(() => {
                    this.serverHtml = '';
                });
        },
    },
});
