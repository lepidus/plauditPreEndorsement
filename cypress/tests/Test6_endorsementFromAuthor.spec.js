const submissionsPage = Cypress.env('baseUrl') + 'index.php/publicknowledge/submissions';
const endorserEmail = "joao.silva@lepidus.com.br";

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

function addContributor() {
    cy.contains('Add Contributor').click();
    cy.wait(250);
    cy.get('input[id^="givenName-en_US"]').type("João", {delay: 0});
    cy.get('input[id^="familyName-en_US"]').type("Silva", {delay: 0});
    cy.get('select[id=country]').select("Brasil");
    cy.get('input[id^="email"]').type(endorserEmail, {delay: 0});
    cy.get('label').contains("Author").click();
    cy.get('#editAuthor > .formButtons > .submitFormButton').click();
}

function submissionStep3() {
    cy.get('input[name^="title"]').first().type("Submission test endorsement from author", { delay: 0 });
    cy.get('label').contains('Title').click();
    cy.get('textarea[id^="abstract-"').then((node) => {
        cy.setTinyMceContent(node.attr("id"), "Example of abstract");
    });
    cy.get('.section > label:visible').first().click();

    addContributor();

    cy.get('ul[id^="en_US-keywords-"]').then(node => {
        node.tagit('createTag', "Dummy keyword");
    });
    cy.get('input[name^="endorserName"]').type("João Silva", { delay: 0 });
    cy.get('input[name^="endorserEmail"]').type(endorserEmail, { delay: 0 });
    cy.get('#submitStep3Form > .formButtons > .submitFormButton').click();
}

describe("Plaudit Pre-Endorsement Plugin - Endorsement from author", function() {
    it("Checks alert message for endorsement given by an author", function() {
        cy.visit(submissionsPage);
        loginAdminUser();
        cy.contains("Submissions").click();

        cy.get('.pkpHeader__actions:visible > a.pkpButton').click();
        submissionStep1();
        submissionStep2();
        submissionStep3();
        cy.contains("The endorsement cannot be given by any of the authors of the manuscript");
    });
});