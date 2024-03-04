import '../support/commands.js';

describe("Plaudit Pre-Endorsement Plugin - Workflow features", function() {
    let submissionTitle = "Killers of the Flower Moon";

    it("E-mail sendings counting message in submission workflow", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
        cy.get("#plauditPreEndorsement").contains("Save").click();
        
        cy.reload();
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains("2 endorsement confirmation e-mails have been sent to the endorser");
    });
    it("E-mail sendings count set to zero when endorser is changed", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionTitle);
        
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.get('input[name="endorserNameWorkflow"]').clear().type("Lady Diana", { delay: 0 });
        cy.get('input[name="endorserEmailWorkflow"]').clear().type("lady.diana@gmail.com", { delay: 0 });
        cy.get("#plauditPreEndorsement").contains("Save").click();
        
        cy.reload();
        cy.get("#publication-button").click();
        cy.contains("Pre-Endorsement").click();
        cy.contains("1 endorsement confirmation e-mail has been sent to the endorser");
    });
});