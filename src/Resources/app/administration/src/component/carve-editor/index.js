import template from './carve-editor.html.twig';
import './carve-editor.scss';
import { PreviewRequest, escapeInline, administrationLanguageId } from './preview-request';

Shopware.Component.register('carve-editor', {
    template,
    inject: ['repositoryFactory', 'acl'],
    inheritAttrs: false,
    emits: ['update:value', 'update:modelValue'],
    props: {
        value: { type: String, default: '' },
        modelValue: { type: String, default: undefined },
        label: { type: [String, Object], default: 'Carve source' },
        disabled: { type: Boolean, default: false },
        readOnly: { type: Boolean, default: false },
        includes: { type: Boolean, default: false },
        namespace: { type: String, default: null },
        error: { type: Object, default: null },
    },
    data() {
        return {
            preview: null, previewError: null, loading: false, previewEnabled: false,
            salesChannelId: null, productId: null, mediaId: null,
            includePaths: [], includePath: null, includeSection: '', includeShift: '',
            importFormat: 'html', importSource: '', imported: null, importError: null,
            request: null,
        };
    },
    computed: {
        languageId() {
            return administrationLanguageId(Shopware);
        },
        source() { return this.modelValue ?? this.value ?? ''; },
        canInclude() { return this.includes && this.acl.can('carve.include_expand'); },
        textareaComponent() {
            return Shopware.Component.getComponentRegistry().has('mt-textarea') ? 'mt-textarea' : 'sw-textarea-field';
        },
        previewDocument() {
            return '<!doctype html><html><head><meta charset="utf-8"><style>'
                + 'body{font:14px system-ui;color:#222;margin:12px}img{max-width:100%;height:auto}'
                + 'table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:6px}'
                + 'pre{white-space:pre-wrap}iframe{max-width:100%}.admonition{border-left:4px solid #888;padding:8px}'
                + '</style></head><body>' + (this.preview?.html ?? '') + '</body></html>';
        },
        diagnostics() { return [...(this.preview?.diagnostics ?? []), ...(this.preview?.warnings ?? [])]; },
        includeOptions() { return this.includePaths.map((path) => ({ value: path, label: path })); },
        importOptions() { return [{ value: 'html', label: 'HTML' }, { value: 'markdown', label: 'Markdown' }]; },
    },
    created() {
        const service = Shopware.Service('syncService');
        const http = service.httpClient;
        this.request = new PreviewRequest(
            (payload) => http.post('/_action/carve/preview', payload, { headers: service.getBasicHeaders() }).then((response) => response.data),
            ({ loading, error, result }) => {
                this.loading = loading;
                this.previewError = error ? (error.response?.data?.errors?.[0]?.detail
                    ?? 'Preview failed. Check your connection and permissions, then retry.') : null;
                this.preview = result;
            },
        );
        Shopware.Service('systemConfigApiService').getValues('ShopwareCarve.config')
            .then((values) => {
                const setting = values['ShopwareCarve.config.livePreview'];
                this.previewEnabled = setting === undefined || setting === null
                    ? true : [true, 'true', 1, '1'].includes(setting);
                if (this.previewEnabled) this.refreshPreview();
            })
            .catch(() => { this.previewError = 'Could not load preview settings.'; });
        if (this.canInclude && !this.readOnly) {
            http.get('/_action/carve/includes', { headers: service.getBasicHeaders() }).then((response) => {
                this.includePaths = response.data.paths;
            }).catch(() => { this.previewError = 'Could not load shared Carve files.'; });
        }
    },
    beforeUnmount() { this.request?.dispose(); },
    beforeDestroy() { this.request?.dispose(); },
    watch: {
        source() { this.refreshPreview(); },
        salesChannelId() { this.refreshPreview(); },
        languageId() { this.refreshPreview(); },
    },
    methods: {
        updateSource(value) {
            this.$emit('update:value', value);
            this.$emit('update:modelValue', value);
        },
        append(value) {
            if (!this.disabled) this.updateSource(this.source + (this.source ? '\n\n' : '') + value);
        },
        refreshPreview() {
            if (!this.previewEnabled) return;
            this.request?.update({
                source: this.source, includes: this.includes, salesChannelId: this.salesChannelId,
                namespace: this.namespace,
                languageId: this.languageId,
            });
        },
        async insertProduct() {
            if (!this.productId || this.disabled) return;
            try {
                const product = await this.repositoryFactory.create('product').get(this.productId, Shopware.Context.api);
                this.append(':product[' + escapeInline(product.productNumber) + ']');
            } catch (error) { this.previewError = 'Could not load the selected product.'; }
        },
        insertMedia() {
            if (this.mediaId) this.append(':media[' + this.mediaId + ']');
        },
        insertInclude() {
            if (!this.includePath || !this.canInclude) return;
            let directive = '{{ ' + this.includePath;
            if (this.includeSection.trim()) {
                if (!/^[a-zA-Z_][a-zA-Z0-9_-]*$/.test(this.includeSection.trim())) {
                    this.previewError = 'Section IDs must start with a letter or underscore and contain only letters, digits, underscores and hyphens.';
                    return;
                }
                directive += ' #' + this.includeSection.trim();
            }
            if (this.includeShift.trim()) {
                if (!/^[+-]?\d+$/.test(this.includeShift.trim())) {
                    this.previewError = 'Heading shift must be an integer.';
                    return;
                }
                directive += ' @shift:' + this.includeShift.trim();
            }
            this.append(directive + ' }}');
        },
        async convertImport() {
            this.importError = null;
            this.imported = null;
            try {
                const service = Shopware.Service('syncService');
                const response = await service.httpClient.post('/_action/carve/import', {
                    source: this.importSource, format: this.importFormat,
                }, { headers: service.getBasicHeaders() });
                this.imported = response.data;
            } catch (error) { this.importError = 'Conversion failed. Check the input size and permissions.'; }
        },
        applyImport() {
            if (this.imported && !this.disabled) {
                this.updateSource(this.imported.source);
                this.imported = null;
                this.importSource = '';
            }
        },
    },
});
