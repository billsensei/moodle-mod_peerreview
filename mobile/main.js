// Script of the Moodle app page of mod_peerreview. It runs in the context of the page component, so `this` is the page.
// Data and texts come from \mod_peerreview\output\mobile::build_state() (see main.html). Uses the app's site object for
// web service calls (mod_peerreview_get_review, mod_peerreview_save_review, mod_peerreview_view_peerreview).
const raw = this.CONTENT_OTHERDATA.data;
this.state = typeof raw === 'string' ? JSON.parse(raw) : raw;
this.review = null;
this.busy = false;

const site = () => this.CoreSitesProvider.getCurrentSite();

// Log the view, like view.php does. A failure here must not stop the page.
site().write('mod_peerreview_view_peerreview', {cmid: this.state.cmid}).catch(() => null);

// The teacher's switch: release the feedback to the students, or hide it again.
this.setRelease = async (released) => {
    this.busy = true;
    try {
        const result = await site().write('mod_peerreview_set_feedback_release', {cmid: this.state.cmid, released: released});
        this.state.teacher.released = result.released;
    } catch (error) {
        this.CoreDomUtilsProvider.showErrorModal(error);
    } finally {
        this.busy = false;
    }
};

// Open the simple review form for one card of the list.
this.openReview = async (card) => {
    this.busy = true;
    try {
        const review = await site().read(
            'mod_peerreview_get_review',
            {cmid: this.state.cmid, allocid: card.allocid},
            {getFromCache: false, saveToCache: false}
        );
        review.score = review.score === '' ? null : review.score;
        // A chosen level is kept as a string so the radio buttons of the rubric compare it with their value.
        review.criteria.forEach((criterion) => {
            criterion.choice = criterion.levelid ? String(criterion.levelid) : '';
        });
        this.review = review;
    } catch (error) {
        this.CoreDomUtilsProvider.showErrorModal(error);
    } finally {
        this.busy = false;
    }
};

this.closeReview = () => {
    this.review = null;
};

// Show a card as submitted without waiting for the page to be fetched again.
this.markSubmitted = (allocid) => {
    const cards = this.state.todo;
    const card = cards.find((item) => item.allocid === allocid);
    if (card) {
        card.submitted = true;
        card.status = this.state.strings.statussubmitted;
        card.action = this.state.strings.reviewedit;
    }
    const done = cards.filter((item) => item.submitted).length;
    this.state.progress = this.state.strings.reviewsdone.replace('{done}', done).replace('{total}', cards.length);
};

// Show a card as a draft without waiting for the page to be fetched again.
this.markDraft = (allocid) => {
    const card = this.state.todo.find((item) => item.allocid === allocid);
    if (card && !card.submitted) {
        card.status = this.state.strings.statusdraft;
    }
};

// Submit the review. The server checks everything again (reviewer, open window, score range).
this.submitReview = async () => {
    await this.saveReview(false);
};

// Keep the rubric review as a draft: the reviewer can come back and finish it.
this.saveDraft = async () => {
    await this.saveReview(true);
};

// Send the review. Rubric: every criterion needs a level before submitting; a draft may be partly filled.
this.saveReview = async (draft) => {
    const review = this.review;
    const rubric = review.method === 'rubric';
    if (!draft && !rubric && (review.score === null || review.score === undefined || review.score === '')) {
        this.CoreDomUtilsProvider.showErrorModal(this.state.strings.score);
        return;
    }
    if (!draft && rubric && review.criteria.some((criterion) => !criterion.choice)) {
        this.CoreDomUtilsProvider.showErrorModal(this.state.strings.incomplete);
        return;
    }
    this.busy = true;
    try {
        await site().write('mod_peerreview_save_review', {
            cmid: this.state.cmid,
            allocid: review.allocid,
            score: rubric || review.score === null ? '' : String(review.score),
            feedback: review.feedback || '',
            criteria: review.criteria.map((criterion) => ({
                id: criterion.id,
                levelid: criterion.choice ? Number(criterion.choice) : 0,
                remark: criterion.remark || '',
            })),
            draft: draft,
        });
        this.review = null;
        if (draft) {
            this.markDraft(review.allocid);
            this.CoreDomUtilsProvider.showToast(this.state.strings.draftsaved, false, 3000);
            await this.refreshContent(false);
            return;
        }
        this.markSubmitted(review.allocid);
        this.CoreDomUtilsProvider.showToast(this.state.strings.submitted, false, 3000);
        await this.refreshContent(false);
    } catch (error) {
        this.CoreDomUtilsProvider.showErrorModal(error);
    } finally {
        this.busy = false;
    }
};
