import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'carve',
    label: 'Carve',
    component: 'sw-cms-el-carve',
    configComponent: 'sw-cms-el-config-carve',
    previewComponent: 'sw-cms-el-preview-carve',
    defaultConfig: {
        content: { source: 'static', value: '## Carve\n\n*Safe* rich text.' },
    },
});
