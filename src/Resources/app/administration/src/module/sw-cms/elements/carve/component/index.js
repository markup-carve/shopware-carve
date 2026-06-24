import template from './sw-cms-el-carve.html.twig';
import { carveToHtml } from '@markup-carve/carve';

Shopware.Component.register('sw-cms-el-carve', {
    template,
    mixins: [Shopware.Mixin.getByName('cms-element')],
    data() {
        return {
            livePreviewEnabled: true,
        };
    },
    created() {
        this.initElementConfig('carve');
        Shopware.Service('systemConfigApiService')
            .getValues('ShopwareCarve.config')
            .then((values) => {
                const val = values['ShopwareCarve.config.livePreview'];
                this.livePreviewEnabled = val === undefined ? true : Boolean(val);
            });
    },
    computed: {
        html() {
            const src = this.element?.config?.content?.value ?? '';
            try {
                return carveToHtml(src, { allowRawHtml: false });
            } catch (e) {
                return '';
            }
        },
    },
});
