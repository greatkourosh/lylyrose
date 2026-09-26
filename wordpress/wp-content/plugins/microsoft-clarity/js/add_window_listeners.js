const MessageOperation = {
  PROJECT_ID_CHANGE: 1,
  REDIRECT: 2,
  AGENT_ENABLED_CHANGE: 4,
  WORDPRESS_CONNECT: 8,
  WORDPRESS_CONNECTOR_REQUEST: 16,
};

const CONNECTOR_OPERATIONS = {
  status: true,
  connect: true,
  reauthorize: true,
  sync: true,
  disconnect: true,
};

// Origin of the embedded Clarity dashboard iframe. Injected from PHP (wp_localize_script) so it
// matches the actual iframe host in every environment (e.g. the local dev host during testing,
// https://clarity.microsoft.com in production). Falls back to production if the value is missing.
const TRUSTED_CLARITY_ORIGIN =
  (typeof window !== "undefined" && window.clarityBrandAgentConfig && window.clarityBrandAgentConfig.trustedOrigin) ||
  "https://clarity.microsoft.com";

const PROJECT_ID_CHANGE_RESULT = "PROJECT_ID_CHANGE_RESULT";
// Eleven capped delays total 35.5 seconds, long enough to outlast Connect's 30-second HTTP timeout.
const PROJECT_ID_CHANGE_MAX_ATTEMPTS = 12;
const PROJECT_ID_CHANGE_RETRY_BASE_MS = 500;
const PROJECT_ID_CHANGE_RETRY_MAX_MS = 4000;
let activeProjectChangeRequest = null;
let projectChangeAjaxInFlight = false;
let projectChangeRetryTimer = null;

const isValidProjectId = (id) => {
  if (id === null || id === undefined || typeof id !== "string") {
    return false;
  }
  const pattern = /^[a-zA-Z0-9]*$/;
  return pattern.test(id);
};

const respondToProjectChange = (request, success, payload) => {
  // Older dashboards do not send a request id and keep their historical fire-and-forget flow.
  if (!request.source || !request.requestId) return;

  const response = {
    type: PROJECT_ID_CHANGE_RESULT,
    requestId: request.requestId,
    success: !!success,
  };
  if (!success) {
    response.errorCode = (payload && payload.error_code) || "project_id_update_failed";
    response.message = (payload && payload.message) || "Unable to update the Clarity project.";
  }
  request.source.postMessage(response, TRUSTED_CLARITY_ORIGIN);
};

const logProjectChangeResult = (request, success) => {
  const action = request.isRemoveRequest ? "remove" : "add";
  const project = request.isRemoveRequest ? "." : ` for project ${request.postedMessage?.id}.`;
  const result = success
    ? `${request.isRemoveRequest ? "Removed" : "Added"} Clarity snippet`
    : `Failed to ${action} Clarity snippet`;
  console.log(`${result}${project}`);
};

const persistActiveProjectChange = () => {
  if (projectChangeAjaxInFlight || !activeProjectChangeRequest) return;

  const request = activeProjectChangeRequest;
  projectChangeAjaxInFlight = true;

  jQuery
    .ajax({
      method: "POST",
      url: ajaxurl,
      data: {
        action: "edit_clarity_project_id",
        new_value: request.isRemoveRequest ? "" : request.postedMessage?.id,
        user_must_be_admin: request.postedMessage?.userMustBeAdmin,
        nonce: request.postedMessage?.nonce,
      },
      dataType: "json",
    })
    .done(function (json) {
      projectChangeAjaxInFlight = false;

      // A newer request arrived while this AJAX call was running. Its write must run after this
      // one, even if the stale request succeeded, so the newest project always wins.
      if (request !== activeProjectChangeRequest) {
        persistActiveProjectChange();
        return;
      }

      if (json && json.success) {
        logProjectChangeResult(request, true);
        respondToProjectChange(request, true, json);
        activeProjectChangeRequest = null;
        return;
      }

      // Connect owns the project option while it provisions credentials. Retry only that
      // transient conflict; every other error is terminal and must be surfaced immediately.
      if (json && json.error_code === "connect_in_progress" && request.attempt < PROJECT_ID_CHANGE_MAX_ATTEMPTS) {
        const delay = Math.min(
          PROJECT_ID_CHANGE_RETRY_BASE_MS * Math.pow(2, request.attempt - 1),
          PROJECT_ID_CHANGE_RETRY_MAX_MS,
        );
        request.attempt += 1;
        projectChangeRetryTimer = setTimeout(function () {
          projectChangeRetryTimer = null;
          persistActiveProjectChange();
        }, delay);
        return;
      }

      logProjectChangeResult(request, false);
      respondToProjectChange(request, false, json);
      activeProjectChangeRequest = null;
    })
    .fail(function (xhr) {
      projectChangeAjaxInFlight = false;

      if (request !== activeProjectChangeRequest) {
        persistActiveProjectChange();
        return;
      }

      logProjectChangeResult(request, false);
      respondToProjectChange(request, false, xhr && xhr.responseJSON && xhr.responseJSON.data);
      activeProjectChangeRequest = null;
    });
};

const projectActionCallback = (event) => {
  if (event.origin !== TRUSTED_CLARITY_ORIGIN) return;
  const postedMessage = event?.data;
  if (postedMessage?.operation !== MessageOperation.PROJECT_ID_CHANGE || !isValidProjectId(postedMessage?.id)) {
    return;
  }

  const request = {
    postedMessage: postedMessage,
    isRemoveRequest: postedMessage?.id === "",
    source: event.source,
    requestId: typeof postedMessage?.requestId === "string" ? postedMessage.requestId : "",
    attempt: 1,
  };

  if (activeProjectChangeRequest) {
    respondToProjectChange(activeProjectChangeRequest, false, {
      error_code: "project_change_superseded",
      message: "A newer Clarity project change was requested.",
    });
  }
  activeProjectChangeRequest = request;

  if (projectChangeRetryTimer !== null) {
    clearTimeout(projectChangeRetryTimer);
    projectChangeRetryTimer = null;
  }
  persistActiveProjectChange();
};

const agentsActionCallback = (event) => {
  if (event.origin !== TRUSTED_CLARITY_ORIGIN) return;
  const postedMessage = event?.data;
  if (postedMessage?.operation !== MessageOperation.AGENT_ENABLED_CHANGE) return;

  const isRemoveRequest = postedMessage?.status === false;
  const agent_status = postedMessage?.status === false ? 0 : 1;

  jQuery
    .ajax({
      method: "POST",
      url: ajaxurl,
      data: {
        action: "edit_agent_enabled_status",
        new_value: agent_status,
        user_must_be_admin: postedMessage?.userMustBeAdmin,
        nonce: postedMessage?.nonce,
      },
      dataType: "json",
    })
    .done(function (json) {
      if (!json.success) {
        console.log(
          `Failed to ${isRemoveRequest ? "remove" : "add"} Agent snippet${isRemoveRequest ? "." : ` for project ${postedMessage?.id}.`}`,
        );
      } else {
        console.log(
          `${isRemoveRequest ? "Removed" : "Added"} Agent snippet${isRemoveRequest ? "." : ` for project ${postedMessage?.id}.`}`,
        );
      }
    })
    .fail(function () {
      console.log(
        `Failed to ${isRemoveRequest ? "remove" : "add"} Agent snippet${isRemoveRequest ? "." : ` for project ${postedMessage?.id}.`}`,
      );
    });
};

const redirectActionCallback = (event) => {
  const siteOrigin = window.location.origin;
  
  // SECURITY: Only accept messages from Clarity dashboard or our own site
  if (event.origin !== TRUSTED_CLARITY_ORIGIN && event.origin !== siteOrigin) {
    return;
  }
  
  // Check if the message has the correct structure
  const postedMessage = event?.data;
  if (!postedMessage || postedMessage.operation !== MessageOperation.REDIRECT || !postedMessage.redirectURL) {
    return;
  }
  
  const redirectURL = postedMessage.redirectURL;
  
  // SECURITY: Validate the redirect URL is a WordPress admin URL on our domain
  if (redirectURL.indexOf(siteOrigin + "/wp-admin/") !== 0) {
    return;
  }
  
  // SECURITY: Only allow specific WordPress admin pages
  const allowedPages = [
    "/wp-admin/options-permalink.php",
  ];
  
  const pageAllowed = allowedPages.some(page => redirectURL.indexOf(page) !== -1);
  
  if (!pageAllowed) {
    return;
  }
  
  // Open in a new tab (bypasses iframe sandbox restrictions)
  window.open(redirectURL, "_blank");
};

window.addEventListener("message", redirectActionCallback, false);
window.addEventListener("message", agentsActionCallback, false);
window.addEventListener("message", projectActionCallback, false);

// Plain WordPress (no WooCommerce) Brand Agent connect. The Clarity dashboard embedded in the
// wp-admin iframe asks us to run the server-to-server connect (there is no OAuth popup). We call
// the admin-ajax handler with the same admin nonce used for project-id changes, then post the
// result back to the dashboard iframe so it can advance to the setup page or show a retry.
const brandAgentConnectCallback = (event) => {
  if (event.origin !== TRUSTED_CLARITY_ORIGIN) return;
  const postedMessage = event?.data;
  if (postedMessage?.operation !== MessageOperation.WORDPRESS_CONNECT) return;

  const source = event.source;
  const respond = (type) => {
    if (source) {
      source.postMessage({ type: type }, TRUSTED_CLARITY_ORIGIN);
    }
  };

  const requestData = {
    action: "brandagent_wordpress_connect",
    nonce: postedMessage?.nonce,
  };
  if (Object.prototype.hasOwnProperty.call(postedMessage, "projectId")) {
    requestData.project_id = postedMessage.projectId;
  }

  jQuery
    .ajax({
      method: "POST",
      url: ajaxurl,
      data: requestData,
      dataType: "json",
    })
    .done(function (json) {
      respond(json && json.success ? "WORDPRESS_CONNECT_SUCCESS" : "WORDPRESS_CONNECT_FAILURE");
    })
    .fail(function () {
      respond("WORDPRESS_CONNECT_FAILURE");
    });
};

window.addEventListener("message", brandAgentConnectCallback, false);

// Square connector operations. A pending reauthorization replacement is bound to the exact
// authorization version observed by the iframe.
// This parent holds the admin-ajax nonce and never forwards HMAC material back.
const brandAgentConnectorCallback = (event) => {
  if (event.origin !== TRUSTED_CLARITY_ORIGIN) return;
  const postedMessage = event?.data;
  if (postedMessage?.operation !== MessageOperation.WORDPRESS_CONNECTOR_REQUEST) return;

  const source = event.source;
  const requestId = typeof postedMessage.requestId === "string" ? postedMessage.requestId : "";
  const operation = typeof postedMessage.connectorOperation === "string" ? postedMessage.connectorOperation : "";
  const providerId = typeof postedMessage.providerId === "string" ? postedMessage.providerId : "";
  const hasReplacePendingAuthorization = Object.prototype.hasOwnProperty.call(
    postedMessage,
    "replacePendingAuthorization",
  );
  const replacePendingAuthorization = postedMessage.replacePendingAuthorization === true;
  const hasExpectedAuthorizationVersion = Object.prototype.hasOwnProperty.call(
    postedMessage,
    "expectedAuthorizationVersion",
  );
  const expectedAuthorizationVersion = postedMessage.expectedAuthorizationVersion;
  const nonce =
    typeof window !== "undefined" &&
    window.clarityBrandAgentConfig &&
    window.clarityBrandAgentConfig.connectorNonce
      ? window.clarityBrandAgentConfig.connectorNonce
      : "";

  const respond = (success, data, error) => {
    if (!source) {
      return;
    }
    source.postMessage(
      {
        type: "WORDPRESS_CONNECTOR_RESPONSE",
        requestId: requestId,
        success: !!success,
        data: data || null,
        error: error || null,
      },
      TRUSTED_CLARITY_ORIGIN,
    );
  };

  if (!requestId || !CONNECTOR_OPERATIONS[operation] || !nonce) {
    respond(false, null, { code: "invalid_request", message: "Invalid connector request." });
    return;
  }

  const hasPendingReplacementFields =
    hasReplacePendingAuthorization || hasExpectedAuthorizationVersion;
  const validReplacePendingAuthorization =
    !hasReplacePendingAuthorization || typeof postedMessage.replacePendingAuthorization === "boolean";
  const validPendingReplacement =
    operation === "reauthorize" &&
    replacePendingAuthorization &&
    Number.isInteger(expectedAuthorizationVersion) &&
    expectedAuthorizationVersion > 0 &&
    expectedAuthorizationVersion <= 2147483647;
  if (
    (hasPendingReplacementFields &&
      (operation !== "reauthorize" ||
        !validReplacePendingAuthorization ||
        (replacePendingAuthorization ? !validPendingReplacement : hasExpectedAuthorizationVersion)))
  ) {
    respond(false, null, { code: "invalid_request", message: "Invalid connector request." });
    return;
  }

  const requestData = {
    action: "brandagent_connectors",
    nonce: nonce,
    operation: operation,
    providerId: providerId,
  };
  if (validPendingReplacement) {
    requestData.replacePendingAuthorization = true;
    requestData.expectedAuthorizationVersion = expectedAuthorizationVersion;
  }

  jQuery
    .ajax({
      method: "POST",
      url: ajaxurl,
      data: requestData,
      dataType: "json",
    })
    .done(function (json) {
      if (json && json.success) {
        respond(true, json.data, null);
        return;
      }
      const message =
        (json && json.data && json.data.message) || "The connector request failed.";
      const code = (json && json.data && json.data.code) || "connector_request_failed";
      respond(false, null, {
        code: code,
        message: message,
        connector: (json && json.data && json.data.connector) || null,
      });
    })
    .fail(function (xhr) {
      const payload = xhr && xhr.responseJSON && xhr.responseJSON.data;
      respond(false, null, {
        code: (payload && payload.code) || "connector_request_failed",
        message: (payload && payload.message) || "The connector request failed.",
        connector: (payload && payload.connector) || null,
      });
    });
};

window.addEventListener("message", brandAgentConnectorCallback, false);
