import '../support/commands.js';

describe("Plaudit Pre-Endorsement Plugin - Authorization of the endorsements API", function() {
    const apiUrl = 'index.php/publicknowledge/api/v1';
    const submissionTitle = "Killers of the Flower Moon";

    function getEndorsements(submissionId) {
        return cy.request({
            url: `${apiUrl}/endorsements/${submissionId}`,
            method: 'GET',
            failOnStatusCode: false
        });
    }

    function sendEndorsement(submissionId, endorsementId, csrfToken) {
        return cy.request({
            url: `${apiUrl}/endorsements/${submissionId}/${endorsementId}/send`,
            method: 'POST',
            headers: {
                'X-Csrf-Token': csrfToken
            },
            failOnStatusCode: false
        });
    }

    function loadSubmissionAndEndorsementIds() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.request(`${apiUrl}/submissions?count=100`).then((response) => {
            const submission = response.body.items.find((item) => JSON.stringify(item.publications).includes(submissionTitle));
            expect(submission, submissionTitle).to.exist;
            cy.wrap(submission.id).as('submissionId');
        });
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.items).to.not.be.empty;
                cy.wrap(response.body.items[0].id).as('endorsementId');
            });
        });
        cy.logout();
    }

    it("Allows the submitting author but not to send endorsements to Plaudit", function() {
        loadSubmissionAndEndorsementIds();

        cy.login('ckwantes', null, 'publicknowledge');
        cy.getCsrfToken();
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).its('status').should('eq', 200);
            cy.get('@endorsementId').then((endorsementId) => {
                cy.get('@csrfToken').then((csrfToken) => {
                    sendEndorsement(submissionId, endorsementId, csrfToken)
                        .its('status').should('be.oneOf', [401, 403]);
                });
            });
        });
    });

    it("Rejects sending an endorsement not confirmed by the endorser", function() {
        loadSubmissionAndEndorsementIds();

        cy.login('dbarnes', null, 'publicknowledge');
        cy.getCsrfToken();
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).then((response) => {
                const endorsement = response.body.items.find((item) => item.status === 0);
                expect(endorsement, 'endorsement awaiting confirmation').to.exist;

                cy.get('@csrfToken').then((csrfToken) => {
                    sendEndorsement(submissionId, endorsement.id, csrfToken).then((sendResponse) => {
                        expect(sendResponse.status).to.eq(409);
                        expect(sendResponse.body.error).to.contain('Plaudit');
                    });
                });
                getEndorsements(submissionId).then((afterResponse) => {
                    const unchanged = afterResponse.body.items.find((item) => item.id === endorsement.id);
                    expect(unchanged.status).to.eq(0);
                });
            });
        });
    });

    it("Denies an author of other submissions", function() {
        loadSubmissionAndEndorsementIds();

        cy.login('ccorino', null, 'publicknowledge');
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).its('status').should('be.oneOf', [401, 403]);
        });
    });

    it("Denies a moderator not assigned to the submission", function() {
        loadSubmissionAndEndorsementIds();

        cy.login('minoue', null, 'publicknowledge');
        cy.getCsrfToken();
        cy.get('@submissionId').then((submissionId) => {
            getEndorsements(submissionId).its('status').should('be.oneOf', [401, 403]);
            cy.get('@endorsementId').then((endorsementId) => {
                cy.get('@csrfToken').then((csrfToken) => {
                    sendEndorsement(submissionId, endorsementId, csrfToken)
                        .its('status').should('be.oneOf', [401, 403]);
                });
            });
        });
    });
});
