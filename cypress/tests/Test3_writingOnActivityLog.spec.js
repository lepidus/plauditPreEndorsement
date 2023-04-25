const submissionsPage = Cypress.env('baseUrl') + 'index.php/publicknowledge/submissions';

function loginAdminUser() {
    cy.get('input[id=username]').clear();
    cy.get('input[id=username]').type(Cypress.env('OJSAdminUsername'), { delay: 0 });
    cy.get('input[id=password]').type(Cypress.env('OJSAdminPassword'), { delay: 0 });
    cy.get('button[class=submit]').click();
}

function submissionStep1() {
    cy.get('#sectionId').select('1');
    cy.get('#pkp_submissionChecklist > ul > li > label > input').check();
    cy.get('#privacyConsent').check();

    cy.get('#submissionStep1 > .formButtons > .submitFormButton').click();
}

function submissionStep2() {
    cy.get('#submitStep2Form > .formButtons > .submitFormButton').click();
}

function submissionStep3() {
    cy.get('input[name^="title"]').first().type("Submission test writing endorsement on Activity Log", { delay: 0 });
    cy.get('label').contains('Title').click();
    cy.get('textarea[id^="abstract-"').then((node) => {
        cy.setTinyMceContent(node.attr("id"), "Example of abstract");
    });
    cy.get('.section > label:visible').first().click();
    cy.get('ul[id^="en_US-keywords-"]').then(node => {
        node.tagit('createTag', "Dummy keyword");
    });
    cy.get('input[name^="endorserName"]').type("Lívia Andrade", { delay: 0 });
    cy.get('input[name^="endorserEmail"]').type("livia@gmail.com", { delay: 0 });
    cy.get('#submitStep3Form > .formButtons > .submitFormButton').click();
}

function submissionStep4() {
    cy.get('#submitStep4Form > .formButtons > .submitFormButton').click();
    cy.get('.pkp_modal_confirmation > .footer > .ok').click();
}

describe("Plaudit Pre-Endorsement Plugin - Check writing of messages on submission's Activity Log", function() {
    it("Admin user submits endorsed submission", function() {
        cy.visit(submissionsPage);
        loginAdminUser();
        cy.contains("Submissions").click();

        cy.get('.pkpHeader__actions:visible > a.pkpButton').click();
        submissionStep1();
        submissionStep2();
        submissionStep3();
        submissionStep4();
    });
    it("Check messages in submission's Activity Log", function() {
        cy.contains("Proceed to post").click();
        cy.get('.pkpWorkflow__header > .pkpHeader__actions').contains('Activity Log').click();
        cy.get(".pkp_modal_panel").contains("An endorsement confirmation e-mail has been sent to Lívia Andrade (livia@gmail.com)");
    });
});