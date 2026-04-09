Cypress.Commands.add('findSubmission', function(view, title) {
    const viewMap = {
        myQueue: 'mySubmissions',
        active: 'editorial?currentViewId=active',
    };
    const dashboardView = viewMap[view] || view;

    cy.visit('index.php/publicknowledge/dashboard/' + dashboardView);
    cy.contains('table tr', title).within(() => {
        cy.get('button').contains(/Complete submission|View/).click({force: true});
    });
});
