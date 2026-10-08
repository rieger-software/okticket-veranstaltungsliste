(function (blocks, blockEditor, components, element, i18n, serverSideRender) {
    'use strict';

    var el = element.createElement;
    var blockData = window.OKTicketDetailsBlockData || {};
    var seriesOptions = [{ label: i18n.__('Veranstaltung wählen', 'okticket-veranstaltungsliste'), value: '' }]
        .concat(blockData.series || []);
    var fieldOptions = blockData.fields || [];

    blocks.registerBlockType('okticket/veranstaltungsdetails', {
        apiVersion: 3,
        title: i18n.__('OKTicket Veranstaltungsdetails', 'okticket-veranstaltungsliste'),
        description: i18n.__('Stellt die Details einer OKTicket-Veranstaltungsreihe dynamisch dar.', 'okticket-veranstaltungsliste'),
        icon: 'tickets-alt',
        category: 'widgets',
        attributes: {
            eventId: { type: 'string', default: '' },
            fields: {
                type: 'array',
                default: fieldOptions.map(function (field) { return field.value; })
            }
        },
        edit: function (props) {
            var blockProps = blockEditor.useBlockProps({ className: 'okticket-overview-block-preview' });
            var selectedEventId = props.attributes.eventId || '';
            var selectedFields = props.attributes.fields || [];
            var setAttributes = props.setAttributes;

            var toggleField = function (field, checked) {
                var fields = checked
                    ? selectedFields.concat(selectedFields.indexOf(field) === -1 ? [field] : [])
                    : selectedFields.filter(function (value) { return value !== field; });
                setAttributes({ fields: fields });
            };

            return el('div', blockProps,
                el(blockEditor.InspectorControls, {},
                    el(components.PanelBody, {
                        title: i18n.__('Veranstaltung', 'okticket-veranstaltungsliste'),
                        initialOpen: true
                    },
                    el(components.SelectControl, {
                        label: i18n.__('Veranstaltungsreihe', 'okticket-veranstaltungsliste'),
                        value: selectedEventId,
                        options: seriesOptions,
                        onChange: function (id) { setAttributes({ eventId: id }); }
                    })),
                    el(components.PanelBody, {
                        title: i18n.__('Angezeigte Informationen', 'okticket-veranstaltungsliste'),
                        initialOpen: true
                    },
                    fieldOptions.map(function (field) {
                        return el(components.CheckboxControl, {
                            key: field.value,
                            label: field.label,
                            checked: selectedFields.indexOf(field.value) !== -1,
                            onChange: function (checked) { toggleField(field.value, checked); }
                        });
                    }))
                ),
                selectedEventId
                    ? el(serverSideRender, {
                        block: 'okticket/veranstaltungsdetails',
                        attributes: { eventId: selectedEventId, fields: selectedFields }
                    })
                    : el(components.Placeholder, {
                        label: i18n.__('OKTicket Veranstaltungsdetails', 'okticket-veranstaltungsliste'),
                        instructions: i18n.__('Wähle in der rechten Seitenleiste eine Veranstaltungsreihe aus.', 'okticket-veranstaltungsliste')
                    })
            );
        },
        save: function () {
            return null;
        }
    });
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender));
