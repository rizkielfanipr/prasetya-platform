/** Mirrors App\Http\Presenters\ContentPresenter (ADR-0011). Content only: never customer, order, or payment data. */
export type ContentStatus = 'DRAFT' | 'READY_FOR_REVIEW' | 'REVISION_REQUESTED' | 'APPROVED' | 'PUBLISHED' | 'REJECTED';
export type ContentDecision = 'APPROVED' | 'REVISION_REQUESTED' | 'REJECTED';
export type ContentAction = 'edit' | 'editDetails' | 'submit' | 'requestRevision' | 'approve' | 'reject' | 'markPublished';
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
    hasImage: boolean;
    updatedAt: string;
}

export interface ContentPublication {
    publishedAt: string;
    url: string | null;
    note: string | null;
    actor: string;
    revisionId: string;
    revisionNumber: number;
    recordedAt: string;
}

export interface ContentItem extends ContentRow {
    createdAt: string;
    createdBy: string;
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

export interface ContentDecisionEntry {
    decision: ContentDecision;
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

export interface ContentListFilters extends ContentFilters {
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

export interface ContentFact {
    id: string;
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

export type BrandTab = 'rules' | 'pillars' | 'campaigns' | 'facts' | 'activity';

export type CopyField = 'caption' | 'hashtags' | 'alt_text' | 'visual_direction';
export type GenerationStatus = 'QUEUED' | 'RUNNING' | 'SUCCEEDED' | 'FAILED' | 'DISCARDED';

export interface GenerationRun {
    id: string;
    status: GenerationStatus;
    fields: CopyField[];
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
    priceInputPerMillion: number;
    priceOutputPerMillion: number;
    maxOutputTokens: number;
    maxAttempts: number;
    timeoutSeconds: number;
    budget: { spentThisWeek: number; weekLimit: number; runLimit: number; runsPerRevision: number };
    circuitOpenUntil: string | null;
}
