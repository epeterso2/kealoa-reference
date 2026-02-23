/**
 * KEALOA Reference - Gutenberg Blocks Editor Script
 *
 * Registers all KEALOA blocks for the WordPress block editor.
 *
 * @package KEALOA_Reference
 */

(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, RangeControl, Placeholder } = wp.components;
    const { __ } = wp.i18n;
    const { serverSideRender: ServerSideRender } = wp;

    // Get data passed from PHP
    const kealoaData = window.kealoaBlocksData || { rounds: [], persons: [], constructors: [], editors: [] };

    /**
     * KEALOA Rounds Table Block
     */
    registerBlockType('kealoa/rounds-table', {
        title: __('KEALOA Rounds Table', 'kealoa-reference'),
        description: __('Displays a table of all KEALOA rounds with dates, episodes, solutions, and results.', 'kealoa-reference'),
        icon: 'editor-table',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('rounds', 'kealoa-reference'), __('table', 'kealoa-reference')],
        attributes: {
            limit: {
                type: 'number',
                default: 50
            },
            order: {
                type: 'string',
                default: 'DESC'
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { limit, order } = attributes;

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Table Settings', 'kealoa-reference'), initialOpen: true },
                        createElement(RangeControl, {
                            label: __('Number of Rounds', 'kealoa-reference'),
                            value: limit,
                            onChange: function (value) { setAttributes({ limit: value }); },
                            min: 5,
                            max: 200,
                            step: 5
                        }),
                        createElement(SelectControl, {
                            label: __('Order', 'kealoa-reference'),
                            value: order,
                            options: [
                                { label: __('Newest First', 'kealoa-reference'), value: 'DESC' },
                                { label: __('Oldest First', 'kealoa-reference'), value: 'ASC' }
                            ],
                            onChange: function (value) { setAttributes({ order: value }); }
                        })
                    )
                ),
                createElement(ServerSideRender, {
                    block: 'kealoa/rounds-table',
                    attributes: attributes
                })
            );
        },

        save: function () {
            // Server-side rendering
            return null;
        }
    });

    /**
     * KEALOA Round View Block
     */
    registerBlockType('kealoa/round-view', {
        title: __('KEALOA Round View', 'kealoa-reference'),
        description: __('Displays a single KEALOA round with all clues, guesses, and results.', 'kealoa-reference'),
        icon: 'media-document',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('round', 'kealoa-reference'), __('clues', 'kealoa-reference')],
        attributes: {
            roundId: {
                type: 'number',
                default: 0
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { roundId } = attributes;

            // Build options for round selection
            const roundOptions = [
                { label: __('— Select a Round —', 'kealoa-reference'), value: 0 }
            ];

            kealoaData.rounds.forEach(function (round) {
                roundOptions.push({
                    label: round.date + ' (Episode ' + round.episode + ')',
                    value: round.id
                });
            });

            if (!roundId) {
                return createElement(
                    Fragment,
                    null,
                    createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            { title: __('Round Selection', 'kealoa-reference'), initialOpen: true },
                            createElement(SelectControl, {
                                label: __('Select Round', 'kealoa-reference'),
                                value: roundId,
                                options: roundOptions,
                                onChange: function (value) { setAttributes({ roundId: parseInt(value, 10) }); }
                            })
                        )
                    ),
                    createElement(
                        Placeholder,
                        {
                            icon: 'media-document',
                            label: __('KEALOA Round View', 'kealoa-reference'),
                            instructions: __('Select a round from the block settings in the sidebar.', 'kealoa-reference')
                        },
                        createElement(SelectControl, {
                            value: roundId,
                            options: roundOptions,
                            onChange: function (value) { setAttributes({ roundId: parseInt(value, 10) }); }
                        })
                    )
                );
            }

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Round Selection', 'kealoa-reference'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Select Round', 'kealoa-reference'),
                            value: roundId,
                            options: roundOptions,
                            onChange: function (value) { setAttributes({ roundId: parseInt(value, 10) }); }
                        })
                    )
                ),
                createElement(ServerSideRender, {
                    block: 'kealoa/round-view',
                    attributes: attributes
                })
            );
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Person View Block
     */
    registerBlockType('kealoa/person-view', {
        title: __('KEALOA Person View', 'kealoa-reference'),
        description: __('Displays a person\'s KEALOA statistics, round history, and performance metrics.', 'kealoa-reference'),
        icon: 'admin-users',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('person', 'kealoa-reference'), __('player', 'kealoa-reference')],
        attributes: {
            personId: {
                type: 'number',
                default: 0
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { personId } = attributes;

            // Build options for person selection
            const personOptions = [
                { label: __('— Select a Person —', 'kealoa-reference'), value: 0 }
            ];

            kealoaData.persons.forEach(function (person) {
                personOptions.push({
                    label: person.name,
                    value: person.id
                });
            });

            if (!personId) {
                return createElement(
                    Fragment,
                    null,
                    createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            { title: __('Person Selection', 'kealoa-reference'), initialOpen: true },
                            createElement(SelectControl, {
                                label: __('Select Person', 'kealoa-reference'),
                                value: personId,
                                options: personOptions,
                                onChange: function (value) { setAttributes({ personId: parseInt(value, 10) }); }
                            })
                        )
                    ),
                    createElement(
                        Placeholder,
                        {
                            icon: 'admin-users',
                            label: __('KEALOA Person View', 'kealoa-reference'),
                            instructions: __('Select a person from the block settings in the sidebar.', 'kealoa-reference')
                        },
                        createElement(SelectControl, {
                            value: personId,
                            options: personOptions,
                            onChange: function (value) { setAttributes({ personId: parseInt(value, 10) }); }
                        })
                    )
                );
            }

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Person Selection', 'kealoa-reference'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Select Person', 'kealoa-reference'),
                            value: personId,
                            options: personOptions,
                            onChange: function (value) { setAttributes({ personId: parseInt(value, 10) }); }
                        })
                    )
                ),
                createElement(ServerSideRender, {
                    block: 'kealoa/person-view',
                    attributes: attributes
                })
            );
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Constructors Table Block
     */
    registerBlockType('kealoa/constructors-table', {
        title: __('KEALOA Constructors Table', 'kealoa-reference'),
        description: __('Displays a table of constructors with puzzle and clue counts.', 'kealoa-reference'),
        icon: 'hammer',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('constructors', 'kealoa-reference'), __('table', 'kealoa-reference')],
        attributes: {},

        edit: function (props) {
            return createElement(ServerSideRender, {
                block: 'kealoa/constructors-table',
                attributes: props.attributes
            });
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Players Table Block
     */
    registerBlockType('kealoa/persons-table', {
        title: __('KEALOA Players Table', 'kealoa-reference'),
        description: __('Displays a table of all players with rounds played, clues guessed, and accuracy.', 'kealoa-reference'),
        icon: 'groups',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('players', 'kealoa-reference'), __('persons', 'kealoa-reference'), __('table', 'kealoa-reference')],
        attributes: {},

        edit: function (props) {
            return createElement(ServerSideRender, {
                block: 'kealoa/persons-table',
                attributes: props.attributes
            });
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Constructor View Block
     */
    registerBlockType('kealoa/constructor-view', {
        title: __('KEALOA Constructor View', 'kealoa-reference'),
        description: __('Displays a constructor\'s puzzle history and XWordInfo profile.', 'kealoa-reference'),
        icon: 'hammer',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('constructor', 'kealoa-reference'), __('puzzles', 'kealoa-reference')],
        attributes: {
            constructorId: {
                type: 'number',
                default: 0
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { constructorId } = attributes;

            const constructorOptions = [
                { label: __('— Select a Constructor —', 'kealoa-reference'), value: 0 }
            ];

            kealoaData.constructors.forEach(function (constructor) {
                constructorOptions.push({
                    label: constructor.name,
                    value: constructor.id
                });
            });

            if (!constructorId) {
                return createElement(
                    Fragment,
                    null,
                    createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            { title: __('Constructor Selection', 'kealoa-reference'), initialOpen: true },
                            createElement(SelectControl, {
                                label: __('Select Constructor', 'kealoa-reference'),
                                value: constructorId,
                                options: constructorOptions,
                                onChange: function (value) { setAttributes({ constructorId: parseInt(value, 10) }); }
                            })
                        )
                    ),
                    createElement(
                        Placeholder,
                        {
                            icon: 'hammer',
                            label: __('KEALOA Constructor View', 'kealoa-reference'),
                            instructions: __('Select a constructor from the block settings in the sidebar.', 'kealoa-reference')
                        },
                        createElement(SelectControl, {
                            value: constructorId,
                            options: constructorOptions,
                            onChange: function (value) { setAttributes({ constructorId: parseInt(value, 10) }); }
                        })
                    )
                );
            }

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Constructor Selection', 'kealoa-reference'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Select Constructor', 'kealoa-reference'),
                            value: constructorId,
                            options: constructorOptions,
                            onChange: function (value) { setAttributes({ constructorId: parseInt(value, 10) }); }
                        })
                    )
                ),
                createElement(ServerSideRender, {
                    block: 'kealoa/constructor-view',
                    attributes: attributes
                })
            );
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Editors Table Block
     */
    registerBlockType('kealoa/editors-table', {
        title: __('KEALOA Editors Table', 'kealoa-reference'),
        description: __('Displays a table of all editors with clues guessed and accuracy.', 'kealoa-reference'),
        icon: 'edit',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('editors', 'kealoa-reference'), __('table', 'kealoa-reference')],
        attributes: {},

        edit: function (props) {
            return createElement(ServerSideRender, {
                block: 'kealoa/editors-table',
                attributes: props.attributes
            });
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Editor View Block
     */
    registerBlockType('kealoa/editor-view', {
        title: __('KEALOA Editor View', 'kealoa-reference'),
        description: __('Displays an editor\'s puzzle history.', 'kealoa-reference'),
        icon: 'edit',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('editor', 'kealoa-reference'), __('puzzles', 'kealoa-reference')],
        attributes: {
            editorName: {
                type: 'string',
                default: ''
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { editorName } = attributes;

            const editorOptions = [
                { label: __('— Select an Editor —', 'kealoa-reference'), value: '' }
            ];

            kealoaData.editors.forEach(function (editor) {
                editorOptions.push({
                    label: editor.name,
                    value: editor.name
                });
            });

            if (!editorName) {
                return createElement(
                    Fragment,
                    null,
                    createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            { title: __('Editor Selection', 'kealoa-reference'), initialOpen: true },
                            createElement(SelectControl, {
                                label: __('Select Editor', 'kealoa-reference'),
                                value: editorName,
                                options: editorOptions,
                                onChange: function (value) { setAttributes({ editorName: value }); }
                            })
                        )
                    ),
                    createElement(
                        Placeholder,
                        {
                            icon: 'edit',
                            label: __('KEALOA Editor View', 'kealoa-reference'),
                            instructions: __('Select an editor from the block settings in the sidebar.', 'kealoa-reference')
                        },
                        createElement(SelectControl, {
                            value: editorName,
                            options: editorOptions,
                            onChange: function (value) { setAttributes({ editorName: value }); }
                        })
                    )
                );
            }

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Editor Selection', 'kealoa-reference'), initialOpen: true },
                        createElement(SelectControl, {
                            label: __('Select Editor', 'kealoa-reference'),
                            value: editorName,
                            options: editorOptions,
                            onChange: function (value) { setAttributes({ editorName: value }); }
                        })
                    )
                ),
                createElement(ServerSideRender, {
                    block: 'kealoa/editor-view',
                    attributes: attributes
                })
            );
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Version Info Block
     */
    registerBlockType('kealoa/version-info', {
        title: __('KEALOA Version Info', 'kealoa-reference'),
        description: __('Displays the KEALOA plugin and database version numbers.', 'kealoa-reference'),
        icon: 'info-outline',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('version', 'kealoa-reference'), __('info', 'kealoa-reference')],
        attributes: {},

        edit: function (props) {
            return createElement(ServerSideRender, {
                block: 'kealoa/version-info',
                attributes: props.attributes
            });
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Play Game Block
     */
    registerBlockType('kealoa/play-game', {
        title: __('KEALOA Play Game', 'kealoa-reference'),
        description: __('An interactive KEALOA game that lets visitors play a random round.', 'kealoa-reference'),
        icon: 'games',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('game', 'kealoa-reference'), __('play', 'kealoa-reference'), __('quiz', 'kealoa-reference')],
        attributes: {},

        edit: function () {
            return createElement(Placeholder, {
                icon: 'games',
                label: __('KEALOA Play Game', 'kealoa-reference'),
                instructions: __('This block displays an interactive KEALOA game on the front end. Visitors can play a random round and compare their scores with the show\'s players.', 'kealoa-reference')
            });
        },

        save: function () {
            return null;
        }
    });

    /**
     * KEALOA Rounds Stats Block
     */
    registerBlockType('kealoa/rounds-stats', {
        title: __('KEALOA Rounds Stats', 'kealoa-reference'),
        description: __('Displays KEALOA round statistics: total rounds, clues, guesses, correct answers, and accuracy.', 'kealoa-reference'),
        icon: 'chart-bar',
        category: 'widgets',
        keywords: [__('kealoa', 'kealoa-reference'), __('rounds', 'kealoa-reference'), __('stats', 'kealoa-reference'), __('statistics', 'kealoa-reference')],
        attributes: {},

        edit: function (props) {
            return createElement(ServerSideRender, {
                block: 'kealoa/rounds-stats',
                attributes: props.attributes
            });
        },

        save: function () {
            return null;
        }
    });

})(window.wp);
