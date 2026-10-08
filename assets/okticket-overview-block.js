(function (blocks, blockEditor, components, element, i18n, serverSideRender) {
    'use strict';

    var el = element.createElement;
    var blockData = window.OKTicketOverviewBlockData || {};
    var seriesOptions = blockData.series || [];
    var fieldOptions = blockData.fields || [];

    blocks.registerBlockType('okticket/veranstaltungsuebersicht', {
        apiVersion: 3,
        title: i18n.__('OKTicket Veranstaltungsübersicht', 'okticket-veranstaltungsliste'),
        description: i18n.__('Stellt ausgewählte OKTicket-Veranstaltungsreihen dynamisch dar.', 'okticket-veranstaltungsliste'),
        icon: 'tickets-alt',
        category: 'widgets',
        attributes: {
            eventIds: { type: 'array', default: [] },
            fields: {
                type: 'array',
                default: fieldOptions.map(function (field) { return field.value; })
            }
        },
        edit: function (props) {
            var blockProps = blockEditor.useBlockProps({ className: 'okticket-overview-block-preview' });
            var selectedEventIds = props.attributes.eventIds || [];
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
                        title: i18n.__('Angezeigte Veranstaltungen', 'okticket-veranstaltungsliste'),
                        initialOpen: true
                    },
                    el(components.SelectControl, {
                        label: i18n.__('Veranstaltungsreihen', 'okticket-veranstaltungsliste'),
                        help: i18n.__('Mehrere Reihen mit Strg bzw. Cmd auswählen.', 'okticket-veranstaltungsliste'),
                        value: selectedEventIds,
                        options: seriesOptions,
                        multiple: true,
                        onChange: function (ids) {
                            setAttributes({ eventIds: Array.isArray(ids) ? ids : (ids ? [ids] : []) });
                        }
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
                selectedEventIds.length
                    ? el('div', {},
                        el(serverSideRender, {
                            block: 'okticket/veranstaltungsuebersicht',
                            attributes: { eventIds: selectedEventIds, fields: selectedFields }
                        })
                    )
                    : el(components.Placeholder, {
                        label: i18n.__('OKTicket Veranstaltungsübersicht', 'okticket-veranstaltungsliste'),
                        instructions: i18n.__('Wähle in der rechten Seitenleiste eine oder mehrere Veranstaltungsreihen aus.', 'okticket-veranstaltungsliste')
                    })
            );
        },
        save: function () {
            return null;
        }
    });
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender));
