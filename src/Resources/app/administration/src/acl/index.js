/**
 * A dedicated privilege for filesystem includes. CMS editing rights are not it:
 * whoever holds this may make the storefront read any file under the configured
 * containment root.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'carve',
    roles: {
        include_expand: {
            privileges: ['carve.include_expand'],
            dependencies: [],
        },
    },
});
