import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import { get_strings as getStrings } from 'core/str';


/**
 * Retrieves and returns QuestionPy packages from the application server.
 *
 * @returns {[{name: string, icon: string, description: string, id: string, version: string}]} List of packages
 */
const get_packages = () => {
    // TODO:- retrieve packages from application server.
    //      - Add optional search parameter(s).

    // create fake data for now
    let packages = [];
    for (let i = 1; i <= 4; i++) {
        packages.push(
            {
                'id': `${i}`,
                'name': `ExampleType ${i}`,
                'description': `This describes the package ExampleType ${i}. `.repeat(i),
                'author': `Author ${i}`,
                'license': `MIT`,
                'icon': 'https://placeimg.com/480/480/tech/grayscale',
                'version': `0.0.${i}`
            },
        );
    }
    return packages;
};


/**
 * Initializes the package selection modal.
 */
export const init = () => {
    // fetch initial packages
    const init_packages = get_packages();

    // internationalisation
    getStrings([
        {
            key: 'modal_title',
            component: 'qtype_questionpy'
        },
        {
            key: 'modal_load_package',
            component: 'qtype_questionpy'
        },
        ])
        .then(([modal_title, modal_load_package]) => {
            const open_question_type_modal_btn = document.getElementById('open_question_type_modal');
            open_question_type_modal_btn.onclick = () => {
                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: modal_title,
                    body: Templates.render('qtype_questionpy/select_package_modal', {
                        'questionpy_packages': init_packages,
                    }),
                }).then(function (modal) {
                    modal.setSaveButtonText(modal_load_package);
                    modal.getRoot().on(ModalEvents.save, () => {
                        // TODO: check if a package was selected.
                        // const package_id = modal.getRoot().find('form').serialize();
                    });
                    modal.show();
                });
            };
        });
};
