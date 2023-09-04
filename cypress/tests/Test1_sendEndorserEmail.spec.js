const submissionsPage = Cypress.env('baseUrl') + 'index.php/publicknowledge/submissions';

function submissionStep1() {
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
    cy.get('#submitStep3Form > .formButtons > .submitFormButton').click();
    cy.contains("Your submission has been uploaded and is ready to be sent.");
    
    cy.get('a:contains("3. Enter Metadata")').click();
    cy.wait(1000);
    cy.get('input[name="endorserName"]').type("Queen Elizabeth", { delay: 0 });
    cy.get('input[name="endorserEmail"]').type("queen.elizabeth.2nd@gmail.com", { delay: 0 });
    cy.get('#submitStep3Form > .formButtons > .submitFormButton').click();
    cy.wait(1000);
}

function submissionStep4() {
    cy.get('#submitStep4Form > .formButtons > .submitFormButton').click();
    cy.get('.pkp_modal_confirmation > .footer > .ok').click();
}

describe("Plaudit Pre-Endorsement Plugin - Send e-mail to endorser during submission", function() {
    it("Author user submits endorsed submission", function() {
        cy.login('ckwantes', null, 'publicknowledge');

        cy.get('div#myQueue a:contains("New Submission")').click();
        submissionStep1();
        submissionStep2();
        submissionStep3();
        submissionStep4();
    });
    it("Check if e-mail has been sent to endorser", function() {
        cy.visit('http://0.0.0.0:8025/');
        
        cy.contains('Endorsement confirmation').first().click();
        cy.contains('Queen Elizabeth <queen.elizabeth.2nd@gmail.com>');
    });
});