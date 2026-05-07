export type TripNotification = {
    id: string;
    read_at: string | null;
    created_at: string | null;
    trip_id: number | null;
    trip_name: string | null;
    actor_first_name: string | null;
    changed_area: string | null;
    event_type: string | null;
    subject_type: string | null;
    subject_id: number | string | null;
    deep_link: string;
    summary: string | null;
};

export type NotificationsPayload = {
    unread_count: number;
    recent: TripNotification[];
};
