import '../support/commands.js';

describe("Plaudit Pre-Endorsement Plugin - Workflow features", function() {
    let submissionTitle = "Killers of the Flower Moon";

    it("Endorsement information in workflow tab", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.get('input[name="endorserNameWorkflow"]').should('have.value', 'Bong Joon-ho');
        cy.get('input[name="endorserEmailWorkflow"]').should('have.value', 'bong.joon-ho@email.kr');
        cy.contains('The endorsement has not yet been confirmed by the endorser');
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
    });
    it("E-mail sendings counting in workflow tab", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
        cy.get("#plauditPreEndorsement").contains("Save").click();
        
        cy.reload();
        cy.contains("2 endorsement confirmation e-mails have been sent to the endorser");
        cy.get('input[name="endorserNameWorkflow"]').clear().type("Lady Diana", { delay: 0 });
        cy.get('input[name="endorserEmailWorkflow"]').clear().type("lady.diana@gmail.com", { delay: 0 });
        cy.get("#plauditPreEndorsement").contains("Save").click();
        
        cy.reload();
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
    });
});