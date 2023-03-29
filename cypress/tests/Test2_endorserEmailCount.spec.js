function loginAuthorUser() {
    cy.get('input[id=username]').clear();
    cy.get('input[id=username]').type(Cypress.env('OJSAuthorUsername'), { delay: 0 });
    cy.get('input[id=password]').type(Cypress.env('OJSAuthorPassword'), { delay: 0 });
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
    cy.get('input[name^="title"]').first().type("Submission test send e-mail to endorser", { delay: 0 });
    cy.get('label').contains('Title').click();
    cy.get('textarea[id^="abstract-"').then((node) => {
        cy.setTinyMceContent(node.attr("id"), "Example of abstract");
    });
    cy.get('.section > label:visible').first().click();
    cy.get('ul[id^="en_US-keywords-"]').then(node => {
        node.tagit('createTag', "Dummy keyword");
    });
    cy.get('input[name^="endorserName"]').type("Queen Elizabeth", { delay: 0 });
    cy.get('input[name^="endorserEmail"]').type("queen.elizabeth.2nd@gmail.com", { delay: 0 });
    cy.get('#submitStep3Form > .formButtons > .submitFormButton').click();
}

function submissionStep4() {
    cy.get('#submitStep4Form > .formButtons > .submitFormButton').click();
    cy.get('.pkp_modal_confirmation > .footer > .ok').click();
}

describe("Plaudit Pre-Endorsement Plugin - Send e-mail to endorser during submission", function() {
    it("Author user submits endorsed submission", function() {
        cy.visit(Cypress.env('baseUrl') + 'index.php/publicknowledge/submissions');
        loginAuthorUser();

        cy.get('.pkpHeader__actions:visible > a.pkpButton').click();
        submissionStep1();
        submissionStep2();
        submissionStep3();
        submissionStep4();
    });
    it("Check e-mail sendings counting message in submission workflow", function() {
        cy.contains("Review this submission").click();
        cy.contains("Publication").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
    });
});