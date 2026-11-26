<x-axis.drawer id="drawer-team-member-password" mode="edit" icon="fa-key" size="sm"
               breadcrumb="Team" title="Set sign-in details"
               description="Choose a password for this member. Passwords are never displayed or stored in plain text."
               submit="handleResetTeamMemberPassword(event)" submit-label="Save login" busy-label="Saving…">
    <x-axis.drawer.section>
        <x-axis.drawer.field id="tm-login-email-field" label="Login username (email)" for="tm-login-email" name="email" span="full">
            <input type="email" id="tm-login-email" class="axis-input" maxlength="255">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="New password" for="tm-login-password" name="password" required span="full" hint="At least 8 characters.">
            <input type="password" id="tm-login-password" class="axis-input" required minlength="8" autocomplete="new-password">
        </x-axis.drawer.field>
        <x-axis.drawer.field label="Confirm password" for="tm-login-password-confirmation" name="password_confirmation" required span="full">
            <input type="password" id="tm-login-password-confirmation" class="axis-input" required minlength="8" autocomplete="new-password">
        </x-axis.drawer.field>
    </x-axis.drawer.section>
</x-axis.drawer>
