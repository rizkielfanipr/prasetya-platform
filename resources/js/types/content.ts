/** Mirrors App\Http\Presenters\ContentPresenter (ADR-0011). Content only: never customer, order, or payment data. */
/** ADR-0012: one operator drafts, marks Ready, and records the post. */
export type ContentStatus = 'DRAFT' | 'READY' | 'PUBLISHED' | 'ARCHIVED';
export type ContentDecision = 'APPROVED' | 'REVISION_REQUESTED' | 'REJECTED';
export type ContentAction = 'edit' | 'editDetails' | 'markReady' | 'returnToDraft' | 'archive' | 'markPublished';
/** Each workflow action, and why it is unavailable (null when it is allowed). The server enforces the same answers. */
export type ContentActions = Record<ContentAction, string | null>;

export interface ContentBrand {
    code: string;
    name: string;
}

export interface NamedRef {
    id: string;
    name: string;
}

export interface ContentRow {
    id: string;
    title: string;
    status: ContentStatus;
    brand: ContentBrand;
    pillar: NamedRef | null;
    campaign: NamedRef | null;
    topic: string | null;
    plannedAt: string;
    /** YYYY-MM-DD in business time. */
    plannedDay: string;
    revisionNumber: number | null;
    /** Approved and waiting to be posted by hand; a reminder only. */
    dueState: 'overdue' | 'today' | 'upcoming' | null;
    hasImage: boolean;
    updatedAt: string;
}

export interface ContentPublication {
    publishedAt: string;
    url: string | null;
    note: string | null;
    /** The posting checklist confirmed when marked (absent on records from before phase 7). */
    checklist: PostingStep[] | null;
    actor: string;
    revisionId: string;
    revisionNumber: number;
    recordedAt: string;
}

export interface ContentItem extends ContentRow {
    createdAt: string;
    createdBy: string;
    /** The revision marked Ready, while the item is Ready. */
    readyRevisionId: string | null;
    /** Legacy evidence: the revision approved under the retired editorial workflow. */
    approvedRevisionId: string | null;
    publication: ContentPublication | null;
}

export interface ContentAsset {
    id: string;
    url: string;
    downloadUrl: string;
    fileName: string;
    mimeType: string;
    sizeBytes: number;
    width: number;
    height: number;
    checksum: string;
}

export type RevisionArea = 'brief' | 'caption' | 'hashtags' | 'alt_text' | 'visual_direction' | 'image';

export interface ContentDecisionEntry {
    decision: ContentDecision;
    /** For a revision request: the parts to change. */
    areas: RevisionArea[];
    note: string | null;
    actor: string;
    at: string;
}

export interface ContentFields {
    objective: string | null;
    audience: string | null;
    brief: string | null;
    caption: string | null;
    hashtags: string | null;
    altText: string | null;
    visualDirection: string | null;
}

export interface ContentRevision extends ContentFields {
    id: string;
    number: number;
    author: string;
    submittedBy: string | null;
    submittedAt: string | null;
    /** The brand rules version the revision was checked against when submitted. */
    brandRulesVersion: number | null;
    /** Set when AI drafted this revision. */
    generatedBy: { provider: string; model: string | null; promptVersion: string; fields: CopyField[] } | null;
    createdAt: string;
    updatedAt: string;
    asset: ContentAsset | null;
    decisions: ContentDecisionEntry[];
}

export interface ContentFilters {
    brand: string | null;
    pillar: string | null;
    campaign: string | null;
    status: ContentStatus | null;
}

/** Kalender URL state: the week shown, the view, and the list's search and sort. */
export interface ContentCalendarFilters extends ContentFilters {
    week: string;
    view: 'week' | 'list';
    search: string | null;
    sort: 'planned_at' | 'updated_at';
    direction: 'asc' | 'desc';
}

export interface ContentWeek {
    start: string;
    end: string;
    previous: string;
    next: string;
    today: string;
    days: string[];
}

export interface ContentLimits {
    maxHashtags: number;
    captionMax: number;
}

export type CampaignPriority = 'HIGH' | 'NORMAL' | 'LOW';
export type ContentFactCategory = 'GENERAL' | 'SERVICE' | 'PRICE' | 'PROCESS' | 'ADVANTAGE' | 'FAQ' | 'PROMO' | 'POLICY' | 'EVIDENCE';
export type RuleSeverity = 'ERROR' | 'WARNING';

export interface ContentPillar {
    id: string;
    name: string;
    description: string | null;
    targetShare: number;
    isActive: boolean;
}

export interface ContentCampaign {
    id: string;
    name: string;
    objective: string | null;
    startsOn: string;
    endsOn: string;
    priority: CampaignPriority;
    isActive: boolean;
}

export interface PlanOptions {
    pillars: ContentPillar[];
    campaigns: ContentCampaign[];
}

/** A brand with what an item of it can be planned with. */
export type ContentBrandOption = ContentBrand & PlanOptions;

export interface RuleFinding {
    code: string;
    severity: RuleSeverity;
    message: string;
    field: string | null;
}

export interface CompositionEntry {
    brand: ContentBrand;
    total: number;
    unassigned: number;
    pillars: { id: string; name: string; targetShare: number; count: number }[];
}

export interface BrandRules {
    version: number;
    audience: string | null;
    voice: string | null;
    visualGuidance: string | null;
    preferredTerms: string[];
    forbiddenTerms: string[];
    forbiddenClaims: string[];
    allowedCtas: string[];
    requiredHashtags: string[];
    forbiddenHashtags: string[];
    approvedExamples: string[];
    rejectedExamples: string[];
    captionMinLength: number | null;
    captionMaxLength: number | null;
    hashtagMin: number | null;
    hashtagMax: number | null;
    topicRepeatDays: number | null;
    changeNote: string;
    createdBy: string;
    createdAt: string;
}

export interface BrandRulesVersion {
    version: number;
    changeNote: string;
    createdBy: string;
    createdAt: string;
}

export interface BrandKnowledgeVersion {
    version: number;
    sourceText: string;
    chunks: number;
    createdBy: string;
    createdAt: string;
}

export interface ContentFact {
    id: string;
    category: ContentFactCategory;
    statement: string;
    source: string;
    campaign: string | null;
    validUntil: string | null;
    createdBy: string;
    createdAt: string;
    retiredAt: string | null;
    retiredBy: string | null;
    retiredReason: string | null;
}

export type BrandTab = 'rules' | 'pillars' | 'facts' | 'activity';

export type CopyField = 'caption' | 'hashtags' | 'alt_text' | 'visual_direction';
export type GenerationStatus = 'QUEUED' | 'RUNNING' | 'SUCCEEDED' | 'FAILED' | 'DISCARDED';

export interface GenerationRun {
    id: string;
    status: GenerationStatus;
    fields: CopyField[];
    instruction: string | null;
    usedReviewNote: boolean;
    feedback: 'USEFUL' | 'NOT_USEFUL' | null;
    feedbackReason: string | null;
    provider: string;
    model: string | null;
    promptVersion: string;
    inputTokens: number;
    outputTokens: number;
    costUsd: number;
    latencyMs: number | null;
    attempts: number;
    errorCode: string | null;
    errorMessage: string | null;
    requestedBy: string;
    resultRevisionNumber: number | null;
    item: { id: string; title: string };
    createdAt: string;
    finishedAt: string | null;
}

export interface ItemAi {
    /** Why AI copy cannot be requested now, or null. */
    unavailableReason: string | null;
    runs: GenerationRun[];
}

export interface AiStatus {
    provider: string;
    model: string | null;
    unavailableReason: string | null;
    queue: string;
    /** Models the server allows; empty unless a real provider is configured. */
    models: { name: string }[];
    maxOutputTokens: number;
    maxAttempts: number;
    timeoutSeconds: number;
    search: { provider: string; unavailableReason: string | null; maxResults: number; callsThisWeek: number };
    circuitOpenUntil: string | null;
}

export interface EvaluationRow {
    model: string | null;
    promptVersion: string;
    drafts: number;
    useful: number;
    notUseful: number;
    approved: number;
    costUsd: number;
}

export type SourceTrust = 'OFFICIAL' | 'GENERAL' | 'USER_GENERATED';
export type ResearchStatus = 'QUEUED' | 'RUNNING' | 'SUCCEEDED' | 'FAILED';

/** Untrusted page metadata: render as plain text only. */
export interface ResearchSource {
    id: string;
    url: string;
    domain: string;
    title: string;
    summary: string | null;
    trust: SourceTrust;
    origin: 'SEARCH' | 'MANUAL';
    relevance: number | null;
    publishedOn: string | null;
    fetchedAt: string;
    note: string | null;
}

export interface ResearchRun {
    id: string;
    query: string;
    status: ResearchStatus;
    provider: string;
    resultsCount: number;
    droppedCount: number;
    costUsd: number;
    errorMessage: string | null;
    requestedBy: string;
    createdAt: string;
    sources: ResearchSource[];
}

export interface ItemResearch {
    unavailableReason: string | null;
    cited: ResearchSource[];
    runs: ResearchRun[];
    internal: {
        campaigns: { name: string; objective: string | null }[];
        facts: number;
        similar: { id: string; title: string; status: ContentStatus; plannedAt: string; score: number }[];
    };
    suggestedQuery: string;
}

export type PostingStep = 'image' | 'caption' | 'alt_text' | 'account';

export type ToPostRow = ContentRow & { daysLate: number };

/** Ready posts due today or earlier, oldest first; `total` counts beyond the items shown. */
export interface PostingReminders {
    items: ToPostRow[];
    total: number;
}

export interface InsightSnapshot {
    id: string;
    measuredAt: string;
    reach: number;
    views: number | null;
    likes: number | null;
    comments: number | null;
    saves: number | null;
    shares: number | null;
    profileVisits: number | null;
    follows: number | null;
    engagement: number | null;
    note: string | null;
    recordedBy: string;
}

export interface InsightGroup {
    name: string;
    posts: number;
    avgReach: number | null;
    avgEngagement: number | null;
    avgSaves: number | null;
}

export interface PerformanceSummary {
    window: { days: number; from: string };
    totals: { posts: number; measured: number; reach: number; avgReach: number | null; avgEngagement: number | null; saves: number };
    byPillar: InsightGroup[];
    byCampaign: InsightGroup[];
    byTopic: InsightGroup[];
    top: {
        id: string;
        title: string;
        pillar: string | null;
        topic: string | null;
        publishedAt: string | null;
        reach: number;
        saves: number | null;
        engagement: number | null;
        visualDirection: string | null;
    }[];
    missing: { id: string; title: string; publishedAt: string | null }[];
}
