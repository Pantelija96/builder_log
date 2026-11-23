import Truck from '../machines/Truck.js';
import Excavator from '../machines/Excavator.js';
import TruckLog from '../attendances/TruckLog.js';
import ExcavatorLog from '../attendances/ExcavatorLog.js';
import SubcontractorAttendance from '../attendances/SubcontractorAttendance.js';
import WorkerAttendance from '../attendances/WorkerAttendance.js';
import DailyLog from './DailyLog.js';
import Session, { JSONObject, parseJavaDouble } from '../network/Session.js';
import WebUtils from '../network/WebUtils.js';

function* responseRows(responseBody) {
  const rows = new JSONObject(responseBody).getJSONArray('data');
  for (let index = 0; index < rows.length(); index++) yield rows.getJSONObject(index);
}

function subcontractorAttendance(data) {
  return new SubcontractorAttendance(
    data.getInt('id'), data.getInt('daily_log_id'), data.getInt('subcontractor_id'),
    data.getString('started_at'), data.getString('finished_at'), data.optInt('worker_count'),
    data.isNull('note') ? '' : data.getString('note'),
  );
}

export default class DailyLogUtils {
  /**
   * @typedef {Object} DailyLogCallback
   * @property {(dailyLog: DailyLog | null) => void} onSuccess
   * @property {(exception: Error) => void} onFailure
   */

  /**
   * @typedef {Object} WorkerAttendanceCallback
   * @property {(attendance: WorkerAttendance) => void} onSuccess
   * @property {(exception: Error) => void} onFailure
   */

  /**
   * @typedef {Object} SubcontractorAttendanceCallback
   * @property {(attendance: SubcontractorAttendance) => void} onSuccess
   * @property {(exception: Error) => void} onFailure
   */

  /**
   * @typedef {Object} ExcavatorLogCallback
   * @property {(excavatorLog: ExcavatorLog) => void} onSuccess
   * @property {(exception: Error) => void} onFailure
   */

  /**
   * @typedef {Object} TruckLogCallback
   * @property {(truckLog: TruckLog) => void} onSuccess
   * @property {(exception: Error) => void} onFailure
   */

  /**
   * @typedef {Object} MachineAdvancesCallback
   * @property {(advancesByAssignment: Map<number, string>) => void} onSuccess
   * @property {(exception: Error) => void} onFailure
   */

  static getMachineAdvancesForDailyLog(dailyLog, role, callback) {
    const endpoint = 'daily-logs/' + dailyLog.getDailyLogId() + '/worker-attendances?role=' + role;

    WebUtils.sendGETRequest(endpoint, {}, { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        try {
          const advances = new Map();
          for (const attendance of responseRows(responseBody)) {
            if (!attendance.isNull('machine_assignment_id')) {
              advances.set(attendance.getInt('machine_assignment_id'), attendance.getString('advance_payment'));
            }
          }
          callback.onSuccess(advances);
        } catch (exception) {
          callback.onFailure(exception);
        }
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static getAllPlainWorkersWithAttendance(dailyLog, callback) {
    Session.getAllPlainWorkersFromServer({
      onSuccess(workers) {
        DailyLogUtils.getWorkersAttendanceFromServer(dailyLog, {
          onSuccess() {
            callback.onSuccess(workers);
          },
          onFailure(exception) {
            callback.onFailure(exception);
          },
        });
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static getSubcontractorsWithAttendance(dailyLog, callback) {
    Session.getAllSubcontractorsFromServer({
      onSuccess(subcontractors) {
        DailyLogUtils.getSubcontractorAttendancesFromServer(dailyLog, {
          onSuccess() {
            callback.onSuccess(subcontractors);
          },
          onFailure(exception) {
            callback.onFailure(exception);
          },
        });
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static createOrUpdateDailyLog(site, date, callback) {
    const manager = Session.getCurrentUser();
    if (site == null || date == null || manager == null) {
      callback.onFailure(new Error('Site, date and signed-in manager are required'));
      return;
    }

    const managerId = manager.getId();
    const today = date === Session.todayForDailyLog();
    const cache = Session.sessionDailyLogs;
    if (!today) {
      const cached = cache.find(log => log.getConstructionSiteId() === site.getId() && log.getDailyLogDate() === date);
      if (cached) {
        callback.onSuccess(cached);
        return;
      }
    }

    const responseCallback = {
      onSuccess(responseBody) {
        let log = null;
        try {
          const response = new JSONObject(responseBody);
          const data = today ? response.getJSONObject('data') : response.getJSONArray('data').optJSONObject(0);
          if (data != null) {
            log = new DailyLog(data.getInt('id'), data.getInt('construction_site_id'), managerId, date);
            if (!today && !cache.some(entry => entry.getDailyLogId() === log.getDailyLogId())) cache.push(log);
          }
        } catch (exception) {
          callback.onFailure(exception);
          return;
        }
        callback.onSuccess(log);
      },
      onFailure(exception) { callback.onFailure(exception); },
    };

    const headers = { Authorization: Session.getSessionToken() };
    if (today) {
      WebUtils.sendPOSTRequest('construction-sites/' + site.getId() + '/select', '{}', headers, responseCallback);
    } else {
      WebUtils.sendGETRequest('daily-logs?date=' + date + '&construction_site_id=' + site.getId(), {}, headers, responseCallback);
    }
  }

  static createWorkerAttendance(dailyLog, worker, startedAt, finishedAt, advancePayment, callback) {
    const endpoint = 'daily-logs/' + dailyLog.getDailyLogId() + '/worker-attendances';
    const body = {};
    try {
      body.worker_id = worker.getId();
      body.hourly_rate = worker.getHourlyRate();
      body.started_at = startedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + startedAt;
      body.finished_at = finishedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + finishedAt;
      const normalizedAdvance = advancePayment == null ? '' : advancePayment.trim().replaceAll('.', '');
      body.advance_payment = normalizedAdvance === '' || normalizedAdvance === '-' ? 0 : Number(normalizedAdvance);
    } catch (exception) {
      callback.onFailure(exception);
      return;
    }

    WebUtils.sendPOSTRequest(endpoint, JSON.stringify(body), { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        try {
          const data = new JSONObject(responseBody).getJSONObject('data');
          const advance = data.getString('advance_payment');
          const attendance = new WorkerAttendance(
            data.getInt('id'), dailyLog.getDailyLogId(), worker.getId(),
            worker.getFirstName(), worker.getLastName(),
            data.isNull('started_at') ? 'null' : data.getString('started_at'),
            data.isNull('finished_at') ? 'null' : data.getString('finished_at'),
            parseJavaDouble(advance) === 0 ? '0' : advance,
          );
          callback.onSuccess(attendance);
        } catch (exception) {
          callback.onFailure(exception);
        }
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static updateWorkerAttendance(dailyLog, workerAttendanceId, startedAt, finishedAt, advancePayment, callback) {
    const endpoint = 'daily-logs/' + dailyLog.getDailyLogId() + '/worker-attendances/' + workerAttendanceId;
    const body = {};
    try {
      body.started_at = DailyLogUtils.#isEmptyMachineValue(startedAt)
        ? null : dailyLog.getDailyLogDate() + ' ' + startedAt.trim();
      body.finished_at = DailyLogUtils.#isEmptyMachineValue(finishedAt)
        ? null : dailyLog.getDailyLogDate() + ' ' + finishedAt.trim();
      body.advance_payment = DailyLogUtils.#parseAdvancePayment(advancePayment);
    } catch (exception) {
      callback.onFailure(exception);
      return;
    }

    WebUtils.sendPATCHRequest(endpoint, JSON.stringify(body), { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        callback.onSuccess();
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static getWorkersAttendanceFromServer(dailyLog, callback) {
    const endpoint = 'daily-logs/' + dailyLog.getDailyLogId() + '/worker-attendances?role=worker';

    WebUtils.sendGETRequest(endpoint, {}, { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        try {
          const attendances = [];
          for (const data of responseRows(responseBody)) {
            const worker = data.getJSONObject('worker');
            attendances.push(new WorkerAttendance(
              data.getInt('id'), data.getInt('daily_log_id'), data.getInt('worker_id'),
              worker.getString('first_name'), worker.getString('last_name'),
              data.getString('started_at'), data.getString('finished_at'), data.getString('advance_payment'),
            ));
          }
          for (const worker of Session.getAllWorkers()) {
            worker.removeAttendance(dailyLog.getDailyLogId());
          }
          for (const attendance of attendances) {
            for (const worker of Session.getAllWorkers()) {
              if (worker.getId() === attendance.getWorkerId()) {
                worker.setAttendance(dailyLog.getDailyLogId(), attendance);
                break;
              }
            }
          }
          callback.onSuccess();
        } catch (exception) {
          callback.onFailure(exception);
        }
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static createSubcontractorAttendance(dailyLog, subcontractorId, startedAt, finishedAt, numberOfWorkers, note, callback) {
    const endpoint = 'daily-logs/' + dailyLog.getDailyLogId() + '/subcontractors';
    const body = {};
    try {
      body.subcontractor_id = subcontractorId;
      body.worker_count = numberOfWorkers === '-' ? 0 : Number.parseInt(numberOfWorkers, 10);
      body.started_at = startedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + startedAt;
      body.finished_at = finishedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + finishedAt;
      body.note = note.trim() === '' ? null : note;
    } catch (exception) {
      callback.onFailure(exception);
      return;
    }

    WebUtils.sendPOSTRequest(endpoint, JSON.stringify(body), { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        try {
          const data = new JSONObject(responseBody).getJSONObject('data');
          callback.onSuccess(subcontractorAttendance(data));
        } catch (exception) {
          callback.onFailure(exception);
        }
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static updateSubcontractorAttendance(dailyLog, subcontractorAttendanceId, startedAt, finishedAt, numberOfWorkers, note, callback) {
    const endpoint = 'daily-logs/' + dailyLog.getDailyLogId() + '/subcontractors' + '/' + subcontractorAttendanceId;
    const body = {};
    try {
      body.worker_count = numberOfWorkers === '-' ? 0 : Number.parseInt(numberOfWorkers, 10);
      body.started_at = startedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + startedAt;
      body.finished_at = finishedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + finishedAt;
      body.note = note.trim() === '' ? null : note;
      body.reason = 'Korisnički update';
    } catch (exception) {
      callback.onFailure(exception);
      return;
    }

    WebUtils.sendPATCHRequest(endpoint, JSON.stringify(body), { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        callback.onSuccess();
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static getSubcontractorAttendancesFromServer(dailyLog, callback) {
    const endpoint = 'subcontractor-logs?daily_log_id=' + dailyLog.getDailyLogId();

    WebUtils.sendGETRequest(endpoint, {}, { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        try {
          const attendances = [];
          for (const data of responseRows(responseBody)) {
            attendances.push(subcontractorAttendance(data));
          }
          for (const subcontractor of Session.getAllSubcontractors()) {
            subcontractor.removeAttendance(dailyLog.getDailyLogId());
          }
          for (const attendance of attendances) {
            for (const subcontractor of Session.getAllSubcontractors()) {
              if (subcontractor.getSubcontractorId() === attendance.getSubcontractorId()) {
                subcontractor.setAttendance(dailyLog.getDailyLogId(), attendance);
                break;
              }
            }
          }
          callback.onSuccess();
        } catch (exception) {
          callback.onFailure(exception);
        }
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static getAdminSubcontractorAttendancesFromServer(subcontractorId, dateFrom, dateTo, callback) {
    const endpoint = 'subcontractor-logs?subcontractor_id=' + subcontractorId + '&date_from=' +
    dateFrom + '&date_to=' + dateTo + '&sort=date&direction=desc';

    WebUtils.sendGETRequest(endpoint, {}, { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        try {
          const attendances = [];
          const attendancesPerSite = new Map();
          for (const data of responseRows(responseBody)) {
            if (data.isNull('construction_site')) continue;
            const manager = data.getJSONObject('site_manager');
            const constructionSiteId = data.getInt('construction_site_id');
            const constructionSiteName = data.getJSONObject('construction_site').getString('name');
            const managerName = manager.getString('first_name') + ' ' + manager.getString('last_name');
            const attendance = new SubcontractorAttendance(
              data.getInt('id'), data.getInt('subcontractor_id'), data.getString('started_at'), data.getString('finished_at'),
              data.optInt('worker_count'), data.isNull('note') ? '' : data.getString('note'), data.getString('date'), constructionSiteName, managerName,
            );
            attendances.push(attendance);
            if (!attendancesPerSite.has(constructionSiteId)) {
              attendancesPerSite.set(constructionSiteId, []);
            }
            attendancesPerSite.get(constructionSiteId).push(attendance);
          }
          callback.onSuccess(attendances, attendancesPerSite);
        } catch (exception) {
          callback.onFailure(exception);
        }
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static createExcavatorLogManager(dailyLog, machine, workerId, startedAt, finishedAt, note, advancePayment, callback) {
    const endpoint = 'excavator-logs/daily-logs/' + dailyLog.getDailyLogId();
    const body = {};
    try {
      body.machine_id = machine.getMachineId();
      body.worker_id = workerId;
      body.advance_payment = DailyLogUtils.#parseAdvancePayment(advancePayment);
      body.site_manager_started_at = startedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + startedAt;
      body.site_manager_finished_at = finishedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + finishedAt;
      body.note_site_manager = note.trim() === '' ? null : note;
    } catch (exception) {
      callback.onFailure(exception);
      return;
    }

    WebUtils.sendPOSTRequest(endpoint, JSON.stringify(body), { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        try {
          const data = new JSONObject(responseBody).getJSONObject('data');
          const newMachine = new Excavator(machine);
          const log = new ExcavatorLog(
            data.getInt('id'), workerId, dailyLog.getConstructionSiteId(), newMachine,
            data.getString('site_manager_started_at'), data.getString('site_manager_finished_at'),
            data.getString('note_site_manager'), data.getInt('machine_assignment_id'),
          );
          callback.onSuccess(log);
        } catch (exception) {
          callback.onFailure(exception);
        }
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static updateExcavatorLogManager(dailyLog, excavatorLogId, oldStartedAt, newStartedAt, oldFinishedAt, newFinishedAt, oldNote, newNote, oldAdvance, newAdvance, callback) {
    const endpoint = 'excavator-logs/' + excavatorLogId;
    const body = {};
    try {
      if (DailyLogUtils.#parseAdvancePayment(oldAdvance) !== DailyLogUtils.#parseAdvancePayment(newAdvance)) {
        body.advance_payment = DailyLogUtils.#parseAdvancePayment(newAdvance);
      }
      if (DailyLogUtils.#normalizeMachineValue(oldStartedAt) !== DailyLogUtils.#normalizeMachineValue(newStartedAt)) {
        body.site_manager_started_at = DailyLogUtils.#isEmptyMachineValue(newStartedAt)
          ? null : dailyLog.getDailyLogDate() + ' ' + newStartedAt.trim();
      }
      if (DailyLogUtils.#normalizeMachineValue(oldFinishedAt) !== DailyLogUtils.#normalizeMachineValue(newFinishedAt)) {
        body.site_manager_finished_at = DailyLogUtils.#isEmptyMachineValue(newFinishedAt)
          ? null : dailyLog.getDailyLogDate() + ' ' + newFinishedAt.trim();
      }
      if (DailyLogUtils.#normalizeMachineValue(oldNote) !== DailyLogUtils.#normalizeMachineValue(newNote)) {
        body.note_site_manager = DailyLogUtils.#isEmptyMachineValue(newNote) ? null : newNote.trim();
      }
    } catch (exception) {
      callback.onFailure(exception);
      return;
    }

    if (Object.keys(body).length === 0) {
      callback.onSuccess();
      return;
    }

    WebUtils.sendPATCHRequest(endpoint, JSON.stringify(body), { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        callback.onSuccess();
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static createTruckLogManager(dailyLog, truck, workerId, startedAt, finishedAt, note, advancePayment, callback) {
    const endpoint = 'truck-logs/daily-logs/' + dailyLog.getDailyLogId();
    const body = {};
    try {
      body.machine_id = truck.getMachineId();
      body.worker_id = workerId;
      body.advance_payment = DailyLogUtils.#parseAdvancePayment(advancePayment);
      body.site_manager_started_at = startedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + startedAt;
      body.site_manager_finished_at = finishedAt === '-' ? null : dailyLog.getDailyLogDate() + ' ' + finishedAt;
      body.note_site_manager = note.trim() === '' ? null : note;
    } catch (exception) {
      callback.onFailure(exception);
      return;
    }

    WebUtils.sendPOSTRequest(endpoint, JSON.stringify(body), { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        try {
          const data = new JSONObject(responseBody).getJSONObject('data');
          const newMachine = new Truck(truck);
          const log = new TruckLog(
            data.getInt('id'), workerId, dailyLog.getConstructionSiteId(), newMachine,
            data.getString('site_manager_started_at'), data.getString('site_manager_finished_at'),
            data.getString('note_site_manager'), data.isNull('machine_assignment_id') ? null : data.getInt('machine_assignment_id'),
          );
          callback.onSuccess(log);
        } catch (exception) {
          callback.onFailure(exception);
        }
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static updateTruckLogManager(dailyLog, truckLogId, oldStartedAt, newStartedAt, oldFinishedAt, newFinishedAt, oldNote, newNote, oldAdvance, newAdvance, callback) {
    const endpoint = 'truck-logs/' + truckLogId;
    const body = {};
    try {
      if (DailyLogUtils.#parseAdvancePayment(oldAdvance) !== DailyLogUtils.#parseAdvancePayment(newAdvance)) {
        body.advance_payment = DailyLogUtils.#parseAdvancePayment(newAdvance);
      }
      if (DailyLogUtils.#normalizeMachineValue(oldStartedAt) !== DailyLogUtils.#normalizeMachineValue(newStartedAt)) {
        body.site_manager_started_at = DailyLogUtils.#isEmptyMachineValue(newStartedAt)
          ? null : dailyLog.getDailyLogDate() + ' ' + newStartedAt.trim();
      }
      if (DailyLogUtils.#normalizeMachineValue(oldFinishedAt) !== DailyLogUtils.#normalizeMachineValue(newFinishedAt)) {
        body.site_manager_finished_at = DailyLogUtils.#isEmptyMachineValue(newFinishedAt)
          ? null : dailyLog.getDailyLogDate() + ' ' + newFinishedAt.trim();
      }
      if (DailyLogUtils.#normalizeMachineValue(oldNote) !== DailyLogUtils.#normalizeMachineValue(newNote)) {
        body.note_site_manager = DailyLogUtils.#isEmptyMachineValue(newNote) ? null : newNote.trim();
      }
    } catch (exception) {
      callback.onFailure(exception);
      return;
    }

    if (Object.keys(body).length === 0) {
      callback.onSuccess();
      return;
    }

    WebUtils.sendPATCHRequest(endpoint, JSON.stringify(body), { Authorization: Session.getSessionToken() }, {
      onSuccess(responseBody) {
        callback.onSuccess();
      },
      onFailure(exception) {
        callback.onFailure(exception);
      },
    });
  }

  static #parseAdvancePayment(value) {
    return DailyLogUtils.#isEmptyMachineValue(value) ? 0 : Number(value.trim().replaceAll('.', ''));
  }

  static #isEmptyMachineValue(value) {
    return value == null || value.trim() === '' || value.trim() === '-';
  }

  static #normalizeMachineValue(value) {
    return DailyLogUtils.#isEmptyMachineValue(value) ? '' : value.trim();
  }
}
