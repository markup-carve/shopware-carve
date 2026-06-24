import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'carve',
    label: 'Carve',
    category: 'text',
    component: 'sw-cms-block-carve',
    previewComponent: 'sw-cms-preview-carve',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed',
    },
    slots: { content: 'carve' },
});
