/**
 * Profile — its own URL at /portal/profile, rendered by PortalController::profile.
 *
 * Was a client-side view toggle on the project page; now a real page, so it can be
 * linked to, bookmarked and returned to with the back button.
 */

import '../../../css/ozee-ds/index.css';

import { AttentionBox } from '../../ReactComponents/ds';
import { PortalShell } from '../../ReactComponents/portal/PortalChrome';
import { ProfileView } from '../../ReactComponents/portal/ProfileView';
import { usePortalActions } from '../../ReactComponents/portal/usePortal';

export default function Profile({ account, paymentMethods, idTypes, currencies, branding }) {
    const portal = usePortalActions({ account, paymentMethods, projectId: null });

    return (
        <PortalShell
            title="Your details"
            branding={branding}
            account={portal.profile}
            current="profile"
            onSignOut={portal.signOut}
        >
            <div className="ozds-portal-page">
                {portal.error ? (
                    <div style={{ marginBottom: 16 }}>
                        <AttentionBox type="danger" onClose={() => portal.setError('')}>
                            {portal.error}
                        </AttentionBox>
                    </div>
                ) : null}

                <ProfileView
                    account={portal.profile}
                    paymentMethods={portal.paymentMethods}
                    idTypes={idTypes}
                    currencies={currencies}
                    busy={portal.busy}
                    onSaveProfile={portal.saveProfile}
                    onAddMethod={portal.addPaymentMethod}
                    onRemoveMethod={portal.removePaymentMethod}
                    onMakeDefault={portal.makeDefaultPaymentMethod}
                />
            </div>
        </PortalShell>
    );
}
