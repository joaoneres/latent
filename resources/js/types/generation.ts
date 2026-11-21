export type GenerationRow = {
    id: number;
    prompt: string;
    status: string;
    status_label: string;
    estimate: string;
    charge: string | null;
    image_url: string | null;
    size: string;
    quality: string;
    created_at: string;
    error_message: string | null;
};

export type GenerationPagination = {
    type: 'cursor';
    total: number;
    per_page: number;
    next_cursor: string | null;
    prev_cursor: string | null;
};

export type DashboardSnapshot = {
    balance: string;
    in_progress: number;
    succeeded: number;
    failed: number;
    charged: string;
    recent: GenerationRow[];
};
