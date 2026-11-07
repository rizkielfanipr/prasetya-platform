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

export interface ContentRow {
    id: string;
    title: string;
    status: ContentStatus;
    brand: ContentBrand;
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
    createdAt: string;
    updatedAt: string;
    asset: ContentAsset | null;
    decisions: ContentDecisionEntry[];
}

export interface ContentFilters {
    brand: string | null;
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
