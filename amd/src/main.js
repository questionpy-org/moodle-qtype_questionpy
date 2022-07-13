import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';
import {get_strings as getStrings} from 'core/str';


/**
 * Retrieves and returns QuestionPy packages from the application server.
 *
 * @returns {[{name: string, icon: string, description: string, id: string, version: string}]} List of packages
 */
const getPackages = () => {
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
    // Fetch initial packages
    const initPackages = getPackages();

    // Internationalisation
    let strings = [
        {
            key: 'modal_title',
            component: 'qtype_questionpy'
        },
        {
            key: 'modal_load_package',
            component: 'qtype_questionpy'
        },
    ];

    getStrings(strings)
        .then(([modalTitle, modalLoadPackage]) => {
            const openQuestionTypeModalBtn = document.getElementById('open_question_type_modal');
            openQuestionTypeModalBtn.onclick = () => {
                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: modalTitle,
                    body: Templates.render('qtype_questionpy/select_package_modal', {
                        'questionpy_packages': initPackages,
                    }),
                }).done(modal => {
                    modal.setSaveButtonText(modalLoadPackage);
                    modal.getRoot().on(ModalEvents.save, () => {
                        // TODO: check if a package was selected.
                        // const package_id = modal.getRoot().find('form').serialize();
                    });
                    modal.show();
                });
            };
            return;
        })
        .catch(/* TODO. */);
};
