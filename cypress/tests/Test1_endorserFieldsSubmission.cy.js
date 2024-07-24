import '../support/commands.js';

function beginSubmission(submissionData) {
    cy.get('input[name="locale"][value="en"]').click();
    cy.setTinyMceContent('startSubmission-title-control', submissionData.title);
    
    cy.get('input[name="submissionRequirements"]').check();
    cy.get('input[name="privacyConsent"]').check();
    cy.contains('button', 'Begin Submission').click();
}

describe("Plaudit Pre-Endorsement Plugin - Endorser fields in submission wizard", function() {
    let submissionData;
    let dummyPdf;
    let endorsers;
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 4000);
        submissionData = {
            title: "Killers of the Flower Moon",
			abstract: 'A series of murders starts among native americans',
		};
        dummyPdf = [
            {
                'file': 'dummy.pdf',
                'fileName': 'dummy.pdf',
                'mimeType': 'application/pdf',
                'genre': 'Preprint Text'
            }
        ];
        endorsers = {
            invalidEmail: {
                name: 'John Wayne',
                email: 'john.wayne.email'
            },
            isAuthor: {
                name: 'Catherine Kwantes',
                email: 'ckwantes@mailinator.com'
            },
            firstEndorsement: {
                name: 'Bong Joon-ho',
                email: 'bong.joon-ho@email.kr'
            },
            secondEndorsement: {
                name: 'DummyEndorsement',
                email: 'DummyEndorsement@mailinator.com'
            },
        };
    });
    
    it("Checks endorsement fields and validates endorser email", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.get('div#myQueue a:contains("New Submission")').click();

        beginSubmission(submissionData);
        
        cy.contains('h2', 'Endorsement');
        cy.contains('Do you have the endorsement of an experienced researcher in the field of knowledge of the manuscript?');
        cy.contains('If yes, please provide the name and e-mail address of the endorsing researcher. Endorsements can significantly speed up the moderation process.');
        cy.contains('The endorsement cannot be given by one of the authors of the manuscript.');

        cy.get('a[id^="component-plugins-generic-plauditpreendorsement-controllers-grid-endorsementgrid-addEndorsement-button-"]').contains("Add").click(); 
        cy.get('input[name="endorserName"]').clear().type(endorsers.invalidEmail.name, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(endorsers.invalidEmail.email, {delay: 0});
        cy.get('form[id="endorsementForm"]').find('button[id^="submitFormButton-"]').click();
        cy.contains('Errors occurred processing this form');
        cy.contains('Please enter a valid endorser e-mail');
    });

    it("Validates endorser is not an author of the submission", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('a[id^="component-plugins-generic-plauditpreendorsement-controllers-grid-endorsementgrid-addEndorsement-button-"]').contains("Add").click(); 
        cy.get('input[name="endorserName"]').clear().type(endorsers.isAuthor.name, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(endorsers.isAuthor.email, {delay: 0});
        cy.get('form[id="endorsementForm"]').find('button[id^="submitFormButton-"]').click();
        
        cy.contains('The endorsement cannot be given by any of the authors of the manuscript');
    });

    it("Add correct endorsements", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('a[id^="component-plugins-generic-plauditpreendorsement-controllers-grid-endorsementgrid-addEndorsement-button-"]').contains("Add").click(); 
        cy.get('input[name="endorserName"]').clear().type(endorsers.firstEndorsement.name, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(endorsers.firstEndorsement.email, {delay: 0});
        cy.get('form[id="endorsementForm"]').find('button[id^="submitFormButton-"]').click();
        cy.get('a[id^="component-plugins-generic-plauditpreendorsement-controllers-grid-endorsementgrid-addEndorsement-button-"]').contains("Add").click(); 
        cy.get('input[name="endorserName"]').clear().type(endorsers.secondEndorsement.name, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(endorsers.secondEndorsement.email, {delay: 0});
        cy.get('form[id="endorsementForm"]').find('button[id^="submitFormButton-"]').click();
    });

    it("Validates endorsement email exists", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        
        cy.get('a[id^="component-plugins-generic-plauditpreendorsement-controllers-grid-endorsementgrid-addEndorsement-button-"]').contains("Add").click(); 
        cy.get('input[name="endorserName"]').clear().type(endorsers.firstEndorsement.name, {delay: 0});
        cy.get('input[name="endorserEmail"]').clear().type(endorsers.firstEndorsement.email, {delay: 0});
        cy.get('form[id="endorsementForm"]').find('button[id^="submitFormButton-"]').click();
        cy.contains('Errors occurred processing this form');
        cy.contains('The selected email address is already in use by another user.');
    });

    it("Finishes submission with correct endorsements", function() {
        cy.login('ckwantes', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);
        cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
        
        cy.contains('button', 'Continue').click();
        cy.get('h2').contains('Upload Files');
		cy.get('h2').contains('Files');
		cy.addSubmissionGalleys(dummyPdf);

        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        cy.contains('button', 'Continue').click();
        
        cy.contains('button', 'Submit').click();
        cy.get('.modal__panel:visible').within(() => {
            cy.contains('button', 'Submit').click();
        });
        cy.waitJQuery();
        cy.contains('h1', 'Submission complete');
    });
});