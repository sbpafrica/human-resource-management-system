export default function AppLogo() {
  return (
    <>
      <div className="flex aspect-square size-10 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <img
          src="/images/logo.jpg"
          alt="SBP Africa Logo"
          className="size-10 object-cover"
        />
      </div>
      <div className="ml-1 grid flex-1 text-left text-sm">
        <span className="mb-0.5 truncate leading-tight font-semibold">SBP Africa Hub</span>
      </div>
    </>
  );
}
