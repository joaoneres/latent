export type GenerationRow = {
    id: number;
    prompt: string;
    status: string;
    status_label: string;
    amount: string;
    image_url: string | null;
    size: string;
    quality: string;
    created_at: string;
    error_message: string | null;
};

export type DashboardSnapshot = {
    balance: string;
    in_progress: number;
    succeeded: number;
    failed: number;
    charged: string;
    recent: GenerationRow[];
};
